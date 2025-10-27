# Workflow System Example: Purchase Order Approval

## Complete Example: Purchase Order Approval Flow

Here's a complete example showing how the three-model approval architecture works with a Purchase Order:

### 1. Setup: Organization Plugin Approval Rules

First, let's assume we have these approval rules defined in the Organization plugin:

```php
// Approval rules for Purchase Orders at HQ site
Approval::create([
    'code' => 'PO_HQ_UNDER_10K',
    'document_type' => 'purchase_order',
    'site_id' => 1, // HQ
    'amount_min' => 0,
    'amount_max' => 10000,
    'approval_type' => 'single',
    'staff_id' => 5, // Purchasing Manager
    'required_approvers' => 1,
    'from_status' => 'draft',
    'to_status' => 'approved',
    'approval_timeout_days' => 3,
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);

Approval::create([
    'code' => 'PO_HQ_10K_TO_50K',
    'document_type' => 'purchase_order',
    'site_id' => 1, // HQ
    'amount_min' => 10000.01,
    'amount_max' => 50000,
    'approval_type' => 'quorum',
    'eligible_approvers' => 3, // 3 department heads
    'eligible_approvers_list' => [5, 8, 12], // Purchasing Mgr, Finance Mgr, Operations Mgr
    'required_approvers' => 2, // Need 2 out of 3
    'from_status' => 'draft',
    'to_status' => 'approved',
    'approval_timeout_days' => 5,
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);

Approval::create([
    'code' => 'PO_HQ_ABOVE_50K',
    'document_type' => 'purchase_order',
    'site_id' => 1, // HQ
    'amount_min' => 50000.01,
    'amount_max' => null, // No upper limit
    'approval_type' => 'single',
    'staff_id' => 20, // CEO
    'required_approvers' => 1,
    'from_status' => 'draft',
    'to_status' => 'approved',
    'approval_timeout_days' => 7,
    'escalation_enabled' => true,
    'escalation_staff_id' => 21, // Board Chairman
    'is_active' => true,
    'effective_from' => '2024-01-01'
]);
```

### 2. Purchase Order Creation and Workflow Start

```php
// In Procurement plugin controller
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Procurement\Models\PurchaseOrder;

class PurchaseOrderController extends Controller
{
    public function store()
    {
        // Create Purchase Order
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-HQ-2024-00123',
            'vendor_id' => 10,
            'site_id' => 1, // HQ
            'total_amount' => 35000.00,
            'currency' => 'MYR',
            'status' => 'draft',
            'budget_type' => 'Operating',
            'urgency' => 'normal',
            'created_by' => BackendAuth::getUser()->id
        ]);
        
        // Add line items...
        // $purchaseOrder->lineItems()->create([...]);
        
        // Start approval workflow
        $workflowService = new WorkflowService();
        
        try {
            $workflow = $workflowService->startPurchaseOrderWorkflow($purchaseOrder, [
                'notes' => 'New equipment purchase for IT department',
                'document_attributes' => [
                    'urgency' => 'normal',
                    'budget_type' => 'Operating',
                    'transaction_category' => 'Equipment'
                ]
            ]);
            
            Flash::success("Purchase Order created and sent for approval. Workflow: {$workflow->workflow_code}");
            
        } catch (\Exception $e) {
            Flash::error("Failed to start approval workflow: " . $e->getMessage());
        }
        
        return redirect()->back();
    }
}
```

### 3. Automatic Approval Path Determination

The `ApprovalPathService` automatically determines the path:

```php
// ApprovalPathService::determineApprovalPath() is called
// Input: document_type='purchase_order', amount=35000, site_id=1

// Step 1: Get applicable rules
$rules = Approval::where('document_type', 'purchase_order')
    ->where('site_id', 1)
    ->where('is_active', true)
    ->where('effective_from', '<=', now())
    ->where(function($q) {
        $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
    })
    ->get();

// Step 2: Filter by amount
// Amount 35000 falls into 'PO_HQ_10K_TO_50K' rule (10000.01 to 50000)

// Step 3: Return approval path
return [4]; // Rule ID for PO_HQ_10K_TO_50K (quorum approval)
```

### 4. Workflow Instance Creation

The `WorkflowService` creates a workflow instance:

```php
WorkflowInstance::create([
    'workflow_code' => 'WF-PUR-202401-PO-HQ-2024-00123',
    'status' => 'pending',
    'document_type' => 'purchase_order',
    'documentable_type' => 'Omsb\Procurement\Models\PurchaseOrder',
    'documentable_id' => 123, // PO ID
    'document_amount' => 35000.00,
    'current_step' => 'Quorum Approval (2 of 3)',
    'total_steps_required' => 1,
    'steps_completed' => 0,
    'approval_path' => [4], // JSON array of rule IDs
    'current_approval_rule_id' => 4,
    'current_approval_type' => 'quorum',
    'approvals_required' => 2,
    'approvals_received' => 0,
    'started_at' => '2024-01-15 10:30:00',
    'due_at' => '2024-01-20 10:30:00', // 5 days timeout
    'site_id' => 1,
    'created_by' => 15, // Current user
]);
```

### 5. Approval Actions

#### First Approval (Purchasing Manager):

```php
// In backend controller or API endpoint
use Omsb\Workflow\Services\WorkflowActionService;

$actionService = new WorkflowActionService();
$workflow = WorkflowInstance::where('workflow_code', 'WF-PUR-202401-PO-HQ-2024-00123')->first();

// Purchasing Manager (ID: 5) approves
$result = $actionService->approve($workflow, [
    'comments' => 'Approved - vendor pricing is competitive'
]);

if ($result === true) {
    // Approval recorded, but workflow still pending (needs 2 approvals)
    // Creates WorkflowAction record:
    /*
    WorkflowAction::create([
        'workflow_instance_id' => $workflow->id,
        'approval_rule_id' => 4,
        'step_name' => 'Quorum Approval (2 of 3)',
        'action_type' => 'approve',
        'action_by' => 5, // Purchasing Manager
        'action_at' => now(),
        'comments' => 'Approved - vendor pricing is competitive'
    ]);
    */
    
    // Workflow updated: approvals_received = 1 (still needs 1 more)
}
```

#### Second Approval (Finance Manager):

```php
// Finance Manager (ID: 8) approves
$result = $actionService->approve($workflow, [
    'comments' => 'Budget allocation confirmed'
]);

if ($result === 'completed') {
    // Workflow completed! 
    // - approvals_received = 2 (meets requirement)
    // - workflow status = 'completed'
    // - PO status = 'approved'
    // - No more steps in approval_path
    
    Flash::success('Purchase Order fully approved and ready for processing');
}
```

### 6. Alternative: Rejection Scenario

```php
// If Operations Manager (ID: 12) rejects instead:
$result = $actionService->reject($workflow, [
    'comments' => 'Equipment specification does not meet operational requirements'
]);

if ($result === 'rejected') {
    // Workflow rejected:
    // - workflow status = 'rejected'
    // - PO status = 'rejected'
    // - No further approvals possible
    
    Flash::error('Purchase Order rejected by Operations Manager');
}
```

### 7. Workflow History and Audit Trail

```php
// Get complete approval history
$history = $actionService->getWorkflowHistory($workflow);

foreach ($history as $action) {
    echo "{$action->actionBy->full_name} {$action->action_type}d on {$action->action_at}\n";
    echo "Comments: {$action->comments}\n";
    echo "---\n";
}

// Output:
// John Smith approved on 2024-01-15 14:30:00
// Comments: Approved - vendor pricing is competitive
// ---
// Jane Doe approved on 2024-01-16 09:15:00  
// Comments: Budget allocation confirmed
// ---
```

### 8. Integration with Procurement Operations

```php
// After workflow completion, Purchase Order can proceed
if ($purchaseOrder->status === 'approved') {
    // Send to vendor
    // Create goods receipt notes when delivered
    // Process payments
    // etc.
}
```

## Key Benefits of This Architecture:

1. **Automatic Path Determination**: No manual approval routing needed
2. **Flexible Approval Types**: Single, quorum, position-based approvals
3. **Complete Audit Trail**: Every action tracked with timestamps and comments
4. **Business Rule Enforcement**: Amount limits, site restrictions, timeout handling
5. **Escalation Support**: Automatic escalation for overdue approvals
6. **Cross-Plugin Integration**: Works with any document type across all OMSB plugins

This system handles complex approval scenarios while maintaining clean separation between approval definitions (Organization), workflow execution (Workflow), and business documents (Procurement, Inventory, etc.).