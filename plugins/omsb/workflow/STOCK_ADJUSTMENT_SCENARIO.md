# Stock Adjustment Approval Scenario Analysis

## Scenario: RM175,000 Stock Adjustment at Limbang Warehouse

### Background:
- **Location**: Limbang Warehouse (remote site)
- **Event**: Physical count reveals huge discrepancies
- **Action**: Storekeeper creates Stock Adjustment for RM175,000
- **Company SOP**: 
  - < RM150K: CFO approval required
  - ≥ RM150K: CEO + one Board Director approval required

### Problem Identified:
The CEO received approval request directly, bypassing proper approval hierarchy and causing confusion when CFO was unaware of the situation.

---

## How Our Three-Model System Resolves This Issue

### 1. **Proper Approval Rule Setup** (Organization Plugin)

First, let's set up the correct approval rules that reflect the company SOP:

```php
// Rule 1: Stock Adjustments under RM150K - CFO approval
Approval::create([
    'code' => 'SA_UNDER_150K_CFO',
    'document_type' => 'stock_adjustment',
    'site_id' => null, // Applies to all sites
    'amount_min' => 0,
    'amount_max' => 149999.99,
    'approval_type' => 'single',
    'staff_id' => 101, // CFO at HQ
    'required_approvers' => 1,
    'from_status' => 'submitted',
    'to_status' => 'approved',
    'approval_timeout_days' => 3,
    'floor_limit' => 0,
    'ceiling_limit' => 149999.99,
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);

// Rule 2: Stock Adjustments RM150K and above - CEO + Board Director
Approval::create([
    'code' => 'SA_150K_ABOVE_CEO_BOARD',
    'document_type' => 'stock_adjustment',
    'site_id' => null, // Applies to all sites
    'amount_min' => 150000.00,
    'amount_max' => null, // No upper limit
    'approval_type' => 'quorum',
    'eligible_approvers' => 4, // CEO + 3 Board Directors
    'eligible_approvers_list' => [100, 201, 202, 203], // CEO, Board Dir 1, 2, 3
    'required_approvers' => 2, // CEO + 1 Board Director
    'from_status' => 'submitted',
    'to_status' => 'approved',
    'approval_timeout_days' => 7,
    'floor_limit' => 150000.00,
    'ceiling_limit' => null,
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);

// CRUCIAL: Add mandatory CFO review for high-value adjustments
Approval::create([
    'code' => 'SA_150K_ABOVE_CFO_REVIEW',
    'document_type' => 'stock_adjustment',
    'site_id' => null,
    'amount_min' => 150000.00,
    'amount_max' => null,
    'approval_type' => 'single',
    'staff_id' => 101, // CFO
    'required_approvers' => 1,
    'from_status' => 'submitted',
    'to_status' => 'cfo_reviewed', // Intermediate status
    'approval_timeout_days' => 2,
    'floor_limit' => 0, // Lower priority in sort order
    'ceiling_limit' => null,
    'is_mandatory_step' => true, // Must execute before CEO approval
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);
```

### 2. **Automatic Approval Path Calculation** 

When the Limbang storekeeper submits the RM175,000 Stock Adjustment:

```php
// ApprovalPathService::determineApprovalPath() calculates:
$documentType = 'stock_adjustment';
$amount = 175000.00;
$siteId = 15; // Limbang Warehouse site ID

// System finds applicable rules:
// 1. SA_150K_ABOVE_CFO_REVIEW (floor_limit: 0, mandatory step)
// 2. SA_150K_ABOVE_CEO_BOARD (floor_limit: 150000)

// Calculated approval path: [Rule_CFO_Review_ID, Rule_CEO_Board_ID]
// This ensures CFO MUST review before CEO sees it
```

### 3. **Workflow Execution** (Workflow Plugin)

```php
// WorkflowInstance created:
WorkflowInstance::create([
    'workflow_code' => 'WF-SA-202410-LIMBANG-SA-001',
    'status' => 'pending',
    'document_type' => 'stock_adjustment',
    'documentable_type' => 'Omsb\Inventory\Models\StockAdjustment',
    'documentable_id' => 456,
    'document_amount' => 175000.00,
    'current_step' => 'CFO Review (Financial Impact Assessment)',
    'total_steps_required' => 2,
    'steps_completed' => 0,
    'approval_path' => [25, 26], // CFO Review → CEO + Board Director
    'current_approval_rule_id' => 25, // CFO Review first
    'current_approval_type' => 'single',
    'approvals_required' => 1,
    'approvals_received' => 0,
    'site_id' => 15, // Limbang
    'created_by' => 304 // Storekeeper
]);
```

### 4. **Proper Approval Flow Execution**

#### Step 1: CFO Review (Mandatory)
```php
// CFO receives notification for review
// CFO can:
// a) Approve → Advances to CEO + Board Director step
// b) Reject → Returns to storekeeper with comments
// c) Request more information → Workflow paused

$actionService = new WorkflowActionService();

// CFO approves after investigation
$result = $actionService->approve($workflow, [
    'comments' => 'Reviewed discrepancy report. Physical count variance confirmed due to system error in last migration. Warehouse Manager explanation satisfactory. Recommend CEO approval.'
]);

// Workflow automatically advances to Step 2
```

#### Step 2: CEO + Board Director Approval
```php
// Now CEO receives proper context:
// - CFO has already reviewed and approved
// - Detailed comments from CFO available
// - Proper audit trail showing investigation

// CEO approves
$result = $actionService->approve($workflow, [
    'comments' => 'Approved based on CFO recommendation and investigation findings'
]);

// Need 1 more approval from Board Director
// Board Director 1 approves
$result = $actionService->approve($workflow, [
    'comments' => 'Approved. Recommend implementing better cycle count procedures.'
]);

// Workflow completed: status = 'completed'
// Stock Adjustment status = 'approved'
```

### 5. **How This Resolves the Original Problem**

| Problem | How System Resolves |
|---------|-------------------|
| **CEO receives request without context** | CFO **MUST** review first - CEO never sees it until CFO approves |
| **CFO unaware of situation** | CFO is **mandatory first step** - cannot be bypassed |
| **Warehouse Manager shocked by CEO inquiry** | Proper escalation chain: Storekeeper → CFO → CEO + Board |
| **Lack of investigation** | CFO review step requires analysis before executive approval |
| **No audit trail** | Complete workflow history shows who knew what when |

### 6. **Additional Safeguards the System Provides**

#### Notification Chain:
```php
// Step 1: CFO notified
"Stock Adjustment SA-LIMBANG-001 (RM175,000) requires your review before executive approval"

// Step 2: CEO notified (only after CFO approval)
"Stock Adjustment SA-LIMBANG-001 (RM175,000) approved by CFO, requires CEO + Board Director approval"
```

#### Escalation for Delays:
```php
// If CFO doesn't respond in 2 days
"ESCALATION: Stock Adjustment SA-LIMBANG-001 overdue for CFO review - notifying CEO of delay"

// If CEO doesn't respond in 7 days
"ESCALATION: Stock Adjustment SA-LIMBANG-001 overdue for executive approval"
```

#### Rejection Handling:
```php
// If CFO rejects
"Stock Adjustment rejected by CFO: 'Insufficient documentation of variance investigation'"
// Status: rejected, returns to storekeeper

// If CEO rejects after CFO approval  
"Stock Adjustment rejected by CEO despite CFO approval: 'Variance too large, requires external audit'"
// Status: rejected, with full audit trail
```

---

## Key Benefits of This Approach:

✅ **Mandatory Review Chain**: CFO cannot be bypassed for high-value adjustments  
✅ **Proper Context**: Each approver has full history of previous reviews  
✅ **Clear Accountability**: Audit trail shows who approved what and when  
✅ **Escalation Management**: Automatic notifications for delays  
✅ **Flexible Rules**: Easy to adjust thresholds and approval requirements  
✅ **Cross-Site Consistency**: Same rules apply regardless of location  

The three-model system ensures proper governance, prevents bypassing of controls, and provides complete transparency in the approval process.