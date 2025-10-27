# Stock Adjustment Approval Simulation: RM175,000 Discrepancy at Limbang Warehouse

## Complete Data Simulation: Stock Adjustment Workflow

This simulation shows exactly how the three-model approval system handles the Stock Adjustment scenario step-by-step with real data.

### 1. Initial Setup: Approval Rules in Organization Plugin

```php
// Rule 1: CFO Review for all Stock Adjustments ≥RM150K (Mandatory First Step)
Approval::create([
    'id' => 25,
    'code' => 'SA_150K_ABOVE_CFO_REVIEW',
    'document_type' => 'stock_adjustment',
    'site_id' => null, // Applies to all sites
    'amount_min' => 150000.00,
    'amount_max' => null,
    'approval_type' => 'single',
    'staff_id' => 101, // CFO - Sarah Lim
    'required_approvers' => 1,
    'from_status' => 'submitted',
    'to_status' => 'cfo_reviewed',
    'approval_timeout_days' => 2,
    'floor_limit' => 0, // Executes first (lower priority number)
    'ceiling_limit' => null,
    'is_mandatory_step' => true,
    'escalation_enabled' => true,
    'escalation_staff_id' => 100, // CEO if CFO doesn't respond
    'is_active' => true,
    'effective_from' => '2024-01-01',
    'created_at' => '2024-01-01 00:00:00'
]);

// Rule 2: CEO + Board Director for Stock Adjustments ≥RM150K (After CFO Review)
Approval::create([
    'id' => 26,
    'code' => 'SA_150K_ABOVE_CEO_BOARD',
    'document_type' => 'stock_adjustment',
    'site_id' => null,
    'amount_min' => 150000.00,
    'amount_max' => null,
    'approval_type' => 'quorum',
    'eligible_approvers' => 4,
    'eligible_approvers_list' => [100, 201, 202, 203], // CEO + 3 Board Directors
    'required_approvers' => 2, // CEO + 1 Board Director
    'from_status' => 'cfo_reviewed',
    'to_status' => 'approved',
    'approval_timeout_days' => 7,
    'floor_limit' => 150000.00, // Executes after CFO review
    'ceiling_limit' => null,
    'escalation_enabled' => true,
    'escalation_staff_id' => 204, // Board Chairman
    'is_active' => true,
    'effective_from' => '2024-01-01',
    'created_at' => '2024-01-01 00:00:00'
]);
```

### 2. Staff Data (Organization Plugin)

```php
// Key Staff Members
$staff = [
    304 => ['name' => 'Ahmad Razak', 'position' => 'Storekeeper', 'site_id' => 15], // Limbang
    305 => ['name' => 'Siti Aminah', 'position' => 'Warehouse Manager', 'site_id' => 15], // Limbang
    101 => ['name' => 'Sarah Lim', 'position' => 'Chief Financial Officer', 'site_id' => 1], // HQ
    100 => ['name' => 'Dato\' Rahman', 'position' => 'Chief Executive Officer', 'site_id' => 1], // HQ
    201 => ['name' => 'Tan Sri Lee', 'position' => 'Board Director', 'site_id' => 1], // HQ
    202 => ['name' => 'Datuk Wong', 'position' => 'Board Director', 'site_id' => 1], // HQ
    203 => ['name' => 'Datin Salleh', 'position' => 'Board Director', 'site_id' => 1], // HQ
];

// Site Data
$sites = [
    1 => ['name' => 'Headquarters', 'code' => 'HQ', 'location' => 'Kuala Lumpur'],
    15 => ['name' => 'Limbang Warehouse', 'code' => 'LBG', 'location' => 'Limbang, Sarawak']
];
```

### 3. Stock Adjustment Creation (Inventory Plugin)

```php
// Physical Count Results at Limbang Warehouse
use Omsb\Inventory\Models\StockAdjustment;
use Omsb\Workflow\Services\WorkflowService;

// Ahmad Razak (Storekeeper) creates Stock Adjustment after physical count
$stockAdjustment = StockAdjustment::create([
    'id' => 456,
    'adjustment_number' => 'SA-LBG-2025-00001',
    'site_id' => 15, // Limbang Warehouse
    'warehouse_id' => 23, // Main Limbang Warehouse
    'adjustment_type' => 'Physical Count Variance',
    'total_adjustment_value' => 175000.00,
    'currency' => 'MYR',
    'status' => 'draft',
    'adjustment_date' => '2025-10-27',
    'physical_count_date' => '2025-10-25',
    'reason' => 'Significant discrepancies found during annual physical count',
    'created_by' => 304, // Ahmad Razak
    'warehouse_manager_id' => 305, // Siti Aminah
    'created_at' => '2025-10-27 08:30:00'
]);

// Stock Adjustment Line Items (Major Discrepancies)
$adjustmentItems = [
    [
        'purchaseable_item_id' => 1001,
        'item_name' => 'Industrial Generator Set 50KVA',
        'system_quantity' => 12,
        'physical_quantity' => 8,
        'variance_quantity' => -4,
        'unit_cost' => 15000.00,
        'variance_value' => -60000.00,
        'reason' => 'Units not found during physical count - possible theft or transfer not recorded'
    ],
    [
        'purchaseable_item_id' => 1002,
        'item_name' => 'High-Grade Steel Pipes (Grade 316)',
        'system_quantity' => 500,
        'physical_quantity' => 350,
        'variance_quantity' => -150,
        'unit_cost' => 450.00,
        'variance_value' => -67500.00,
        'reason' => 'Shortage discovered - investigating with procurement team'
    ],
    [
        'purchaseable_item_id' => 1003,
        'item_name' => 'Electronic Control Panels',
        'system_quantity' => 25,
        'physical_quantity' => 15,
        'variance_quantity' => -10,
        'unit_cost' => 4750.00,
        'variance_value' => -47500.00,
        'reason' => 'Items missing from designated storage area'
    ]
];

$stockAdjustment->lineItems()->createMany($adjustmentItems);
```

### 4. Workflow Initiation

```php
// Ahmad submits Stock Adjustment for approval
class StockAdjustmentController extends Controller
{
    public function submit($id)
    {
        $stockAdjustment = StockAdjustment::find($id);
        $workflowService = new WorkflowService();
        
        try {
            $workflow = $workflowService->startWorkflow($stockAdjustment, 'stock_adjustment', [
                'notes' => 'Annual physical count completed at Limbang Warehouse. Significant variances require investigation and approval.',
                'document_attributes' => [
                    'adjustment_type' => 'Physical Count Variance',
                    'variance_category' => 'Major Discrepancy',
                    'impact_level' => 'High',
                    'requires_investigation' => true
                ]
            ]);
            
            Flash::success("Stock Adjustment submitted for approval. Workflow: {$workflow->workflow_code}");
            
        } catch (\Exception $e) {
            Flash::error("Failed to start approval workflow: " . $e->getMessage());
        }
    }
}
```

### 5. Automatic Approval Path Calculation

```php
// ApprovalPathService::determineApprovalPath() executes
// Input: document_type='stock_adjustment', amount=175000, site_id=15

// Step 1: Get applicable rules
$rules = Approval::where('document_type', 'stock_adjustment')
    ->where('is_active', true)
    ->where(function($q) {
        $q->whereNull('site_id')->orWhere('site_id', 15);
    })
    ->where('amount_min', '<=', 175000)
    ->where(function($q) {
        $q->whereNull('amount_max')->orWhere('amount_max', '>=', 175000);
    })
    ->get();

// Found rules: [25, 26] (CFO Review, then CEO + Board)

// Step 2: Sort by floor_limit
// Rule 25 (floor_limit: 0) executes first
// Rule 26 (floor_limit: 150000) executes second

// Final approval path: [25, 26]
```

### 6. WorkflowInstance Creation

```php
WorkflowInstance::create([
    'id' => 789,
    'workflow_code' => 'WF-SA-202510-LBG-SA-LBG-2025-00001',
    'status' => 'pending',
    'document_type' => 'stock_adjustment',
    'documentable_type' => 'Omsb\Inventory\Models\StockAdjustment',
    'documentable_id' => 456,
    'document_amount' => 175000.00,
    'current_step' => 'CFO Financial Impact Review',
    'total_steps_required' => 2,
    'steps_completed' => 0,
    'approval_path' => [25, 26], // JSON: CFO Review → CEO + Board
    'current_approval_rule_id' => 25, // CFO Review
    'current_approval_type' => 'single',
    'approvals_required' => 1,
    'approvals_received' => 0,
    'rejections_received' => 0,
    'started_at' => '2025-10-27 08:45:00',
    'due_at' => '2025-10-29 08:45:00', // 2 days for CFO review
    'site_id' => 15, // Limbang
    'created_by' => 304, // Ahmad Razak
    'workflow_notes' => 'Annual physical count completed at Limbang Warehouse. Significant variances require investigation and approval.',
    'metadata' => [
        'total_items_adjusted' => 3,
        'largest_single_variance' => 60000.00,
        'warehouse_manager' => 'Siti Aminah',
        'physical_count_date' => '2025-10-25'
    ],
    'created_at' => '2025-10-27 08:45:00'
]);

// Stock Adjustment status updated to 'submitted'
$stockAdjustment->update(['status' => 'submitted']);
```

### 7. Step 1: CFO Review Process

#### CFO Receives Notification:
```
TO: Sarah Lim (CFO) <sarah.lim@company.com>
SUBJECT: URGENT: Stock Adjustment Review Required - RM175,000 Variance

Stock Adjustment SA-LBG-2025-00001 requires your review before executive approval.

Details:
- Location: Limbang Warehouse
- Amount: RM175,000 (negative variance)
- Created by: Ahmad Razak (Storekeeper)
- Due Date: October 29, 2025

Major Variances:
- Industrial Generators: -RM60,000 (4 units missing)
- Steel Pipes: -RM67,500 (150 units shortage)
- Control Panels: -RM47,500 (10 units missing)

Action Required: Review and approve/reject before CEO approval
Workflow: WF-SA-202510-LBG-SA-LBG-2025-00001
```

#### CFO Investigation and Action:
```php
// Sarah Lim (CFO) investigates the variance
// Calls Warehouse Manager, reviews reports, checks procedures

use Omsb\Workflow\Services\WorkflowActionService;

$actionService = new WorkflowActionService();
$workflow = WorkflowInstance::find(789);

// CFO approves after investigation (October 28, 2025 - 14:20)
$result = $actionService->approve($workflow, [
    'comments' => 'FINANCIAL IMPACT REVIEW COMPLETED:

1. INVESTIGATION FINDINGS:
   - Contacted Warehouse Manager Siti Aminah - confirmed physical count accuracy
   - Reviewed procurement records - items were properly received in Q2 2025
   - Security footage review shows no unauthorized access
   - Likely cause: System migration error in July 2025 caused double-counting

2. FINANCIAL ANALYSIS:
   - Total variance: RM175,000 (0.8% of total inventory value)
   - Insurance coverage applicable for theft/loss scenarios
   - No cash flow impact - inventory revaluation only

3. RECOMMENDATION:
   - Approve adjustment to align system with physical reality
   - Implement monthly cycle counts for high-value items
   - Review system migration data integrity

4. NEXT STEPS:
   - Recommend CEO approval with Board oversight
   - Suggest internal audit review of migration process

APPROVED for executive review.',
    'metadata' => [
        'investigation_completed' => true,
        'contacted_warehouse_manager' => true,
        'reviewed_security_footage' => true,
        'insurance_notified' => true,
        'root_cause' => 'system_migration_error'
    ]
]);

// Creates WorkflowAction record
WorkflowAction::create([
    'id' => 1001,
    'workflow_instance_id' => 789,
    'approval_rule_id' => 25,
    'step_name' => 'CFO Financial Impact Review',
    'action_type' => 'approve',
    'action_by' => 101, // Sarah Lim
    'action_at' => '2025-10-28 14:20:00',
    'comments' => '[CFO detailed comments above]',
    'metadata' => '[metadata above]'
]);

// Workflow advances to Step 2
$workflow->update([
    'current_approval_rule_id' => 26, // CEO + Board Director
    'current_step' => 'Executive Approval (CEO + Board Director)',
    'current_approval_type' => 'quorum',
    'approvals_required' => 2,
    'approvals_received' => 0,
    'steps_completed' => 1,
    'due_at' => '2025-11-04 14:20:00' // 7 days for executive approval
]);

// Stock Adjustment status updated
$stockAdjustment->update(['status' => 'cfo_reviewed']);
```

### 8. Step 2: Executive Approval Process

#### CEO Receives Notification (with CFO Context):
```
TO: Dato' Rahman (CEO) <ceo@company.com>
CC: Board Directors
SUBJECT: Executive Approval Required - Stock Adjustment RM175,000 (CFO Reviewed)

Stock Adjustment SA-LBG-2025-00001 requires CEO + Board Director approval.

CFO REVIEW SUMMARY (Approved by Sarah Lim on Oct 28, 2025):
- Root Cause: System migration error causing double-counting
- Financial Impact: 0.8% of total inventory value
- Investigation: Complete with Warehouse Manager
- Recommendation: Approve with process improvements

DETAILS:
- Location: Limbang Warehouse
- Amount: RM175,000 (negative variance) 
- CFO Investigation: COMPLETED ✓
- Insurance: Notified ✓

Required: CEO + 1 Board Director approval
Workflow: WF-SA-202510-LBG-SA-LBG-2025-00001
```

#### CEO Approval:
```php
// CEO approves (October 30, 2025 - 10:15)
$result = $actionService->approve($workflow, [
    'comments' => 'EXECUTIVE APPROVAL:

Based on CFO comprehensive review and recommendation, I approve this stock adjustment.

Key factors in decision:
1. Thorough CFO investigation completed
2. Root cause identified (system migration error)
3. Reasonable variance percentage (0.8% of total inventory)
4. Proper insurance notifications made
5. Process improvements planned

Approved with directive to implement monthly cycle counts for high-value items.

- Dato\' Rahman, CEO'
]);

// Creates WorkflowAction record
WorkflowAction::create([
    'id' => 1002,
    'workflow_instance_id' => 789,
    'approval_rule_id' => 26,
    'step_name' => 'Executive Approval (CEO + Board Director)',
    'action_type' => 'approve',
    'action_by' => 100, // Dato' Rahman
    'action_at' => '2025-10-30 10:15:00',
    'comments' => '[CEO comments above]'
]);

// Still needs 1 more approval (Board Director)
$workflow->increment('approvals_received'); // Now = 1, needs 2
```

#### Board Director Approval:
```php
// Tan Sri Lee (Board Director) approves (October 30, 2025 - 16:45)
$result = $actionService->approve($workflow, [
    'comments' => 'BOARD OVERSIGHT APPROVAL:

Concur with CEO and CFO assessment. The comprehensive investigation and identified root cause provide sufficient justification for this adjustment.

Board recommendations:
1. Quarterly board reporting on inventory variances >RM100K
2. Annual review of system migration data integrity
3. Consider external audit of inventory management processes

Approved.

- Tan Sri Lee, Board Director'
]);

// Creates WorkflowAction record
WorkflowAction::create([
    'id' => 1003,
    'workflow_instance_id' => 789,
    'approval_rule_id' => 26,
    'step_name' => 'Executive Approval (CEO + Board Director)',
    'action_type' => 'approve',
    'action_by' => 201, // Tan Sri Lee
    'action_at' => '2025-10-30 16:45:00',
    'comments' => '[Board Director comments above]'
]);

// Workflow completed! (approvals_received = 2, meets requirement)
$workflow->update([
    'status' => 'completed',
    'completed_at' => '2025-10-30 16:45:00',
    'steps_completed' => 2
]);

// Stock Adjustment approved
$stockAdjustment->update(['status' => 'approved']);
```

### 9. Final Workflow History

```php
// Complete audit trail
$history = WorkflowAction::where('workflow_instance_id', 789)
    ->with('actionBy')
    ->orderBy('action_at')
    ->get();

/*
WORKFLOW HISTORY: WF-SA-202510-LBG-SA-LBG-2025-00001

1. Oct 28, 2025 14:20 - Sarah Lim (CFO) APPROVED
   "FINANCIAL IMPACT REVIEW COMPLETED: [detailed investigation...]"

2. Oct 30, 2025 10:15 - Dato' Rahman (CEO) APPROVED  
   "EXECUTIVE APPROVAL: Based on CFO comprehensive review..."

3. Oct 30, 2025 16:45 - Tan Sri Lee (Board Director) APPROVED
   "BOARD OVERSIGHT APPROVAL: Concur with CEO and CFO assessment..."

FINAL STATUS: APPROVED
TOTAL APPROVAL TIME: 3 days, 2 hours, 25 minutes
*/
```

### 10. System Benefits Demonstrated

| Issue (Original Scenario) | Solution (Our System) | Evidence |
|---------------------------|----------------------|----------|
| **CEO surprised by request** | CFO reviewed first | CFO approval Oct 28, CEO notified Oct 30 with context |
| **CFO unaware** | CFO mandatory first step | CFO detailed investigation and approval required |
| **Warehouse Manager shocked** | Proper escalation | CFO contacted warehouse manager as part of review |
| **No investigation** | Required CFO review | 4-point investigation completed before CEO sees request |
| **No audit trail** | Complete workflow history | 3 approval actions with detailed comments and timestamps |
| **Bypass possible** | Mandatory sequence | CEO cannot see request until CFO approves |

## Conclusion

The three-model approval system completely resolves the original governance failure by:

1. **Enforcing mandatory CFO review** before executive approval
2. **Providing complete context** to each approver 
3. **Creating comprehensive audit trail** for compliance
4. **Preventing bypass scenarios** through automatic path calculation
5. **Ensuring proper investigation** before high-level decisions

No more surprised CEOs, uninformed CFOs, or shocked warehouse managers!