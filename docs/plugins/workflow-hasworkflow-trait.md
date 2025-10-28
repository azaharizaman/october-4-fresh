# HasWorkflow Trait Documentation

## Overview

The `HasWorkflow` trait provides a convenient, reusable way to add workflow/approval capabilities to any OctoberCMS model. Instead of manually integrating with `WorkflowService` and `WorkflowActionService` in every model, you can simply add this trait and configure a few properties.

**Location**: `Omsb\Workflow\Traits\HasWorkflow`

**Version**: 1.0.0

## Features

### Automatic Workflow Management
- ✅ Polymorphic relationship to workflow instances
- ✅ Convenient methods for workflow operations
- ✅ Automatic status tracking
- ✅ Edit protection during active workflows
- ✅ Deletion protection during active workflows

### Rich API
- `submitToWorkflow()` - Start approval workflow
- `approveWorkflow()` - Approve current step
- `rejectWorkflow()` - Reject with reason
- `cancelWorkflow()` - Cancel active workflow
- `getCurrentWorkflow()` - Get active workflow instance
- `getCurrentStep()` - Get current approval step name
- `getWorkflowProgress()` - Get completion percentage
- `getWorkflowHistory()` - Get complete action history
- `isApproved()`, `isRejected()`, `hasActiveWorkflow()` - Status checks

### Lifecycle Hooks
- Auto-submit on creation (optional)
- Prevent modifications during approval
- Prevent deletion during approval
- Automatic status updates on approval/rejection

## Basic Usage

### 1. Add Trait to Model

```php
<?php namespace YourPlugin\Models;

use Model;

class PurchaseOrder extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    /**
     * REQUIRED: Define your document type code
     * This is used to route to correct approval rules
     */
    protected $workflowDocumentType = 'purchase_order';
}
```

### 2. Submit Document for Approval

```php
// In controller
public function onSubmitForApproval()
{
    $purchaseOrder = PurchaseOrder::find(post('id'));
    
    try {
        $workflow = $purchaseOrder->submitToWorkflow([
            'notes' => 'Urgent equipment purchase',
            'document_attributes' => [
                'urgency' => 'high',
                'budget_type' => 'Capital'
            ]
        ]);
        
        Flash::success("Purchase Order submitted for approval. Workflow: {$workflow->workflow_code}");
        
    } catch (\Exception $e) {
        Flash::error("Submission failed: " . $e->getMessage());
    }
}
```

### 3. Approve Document

```php
public function onApprove()
{
    $purchaseOrder = PurchaseOrder::find(post('id'));
    
    $result = $purchaseOrder->approveWorkflow([
        'comments' => 'Approved - pricing is competitive'
    ]);
    
    if ($result === 'completed') {
        Flash::success('Purchase Order fully approved!');
    } elseif ($result === true) {
        Flash::success('Approval recorded. Awaiting additional approvals.');
    } else {
        Flash::error("Approval failed: {$result}");
    }
}
```

### 4. Reject Document

```php
public function onReject()
{
    $purchaseOrder = PurchaseOrder::find(post('id'));
    
    $result = $purchaseOrder->rejectWorkflow([
        'comments' => 'Budget allocation insufficient for this quarter'
    ]);
    
    if ($result === 'rejected') {
        Flash::warning('Purchase Order rejected.');
    } else {
        Flash::error("Rejection failed: {$result}");
    }
}
```

## Configuration Options

### Required Property

```php
/**
 * Document type code for workflow routing
 * This MUST be defined in your model
 * 
 * @var string
 */
protected $workflowDocumentType = 'purchase_order';
```

### Optional Properties

```php
/**
 * Statuses that allow workflow submission
 * Default: ['draft']
 * 
 * @var array
 */
protected $workflowEligibleStatuses = ['draft', 'pending'];

/**
 * Status to set when workflow starts
 * Default: 'pending_approval'
 * 
 * @var string
 */
protected $workflowPendingStatus = 'pending_approval';

/**
 * Status to set when workflow completes successfully
 * Default: 'approved'
 * 
 * @var string
 */
protected $workflowApprovedStatus = 'approved';

/**
 * Status to set when workflow is rejected
 * Default: 'rejected'
 * 
 * @var string
 */
protected $workflowRejectedStatus = 'rejected';

/**
 * Auto-submit to workflow immediately after creation
 * Default: false
 * 
 * @var bool
 */
protected $workflowAutoSubmit = false;
```

## Complete Example

```php
<?php namespace Omsb\Procurement\Models;

use Model;

/**
 * PurchaseOrder Model
 */
class PurchaseOrder extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_purchase_orders';
    
    /**
     * Workflow configuration
     */
    protected $workflowDocumentType = 'purchase_order';
    protected $workflowEligibleStatuses = ['draft', 'reviewed'];
    protected $workflowPendingStatus = 'pending_approval';
    protected $workflowApprovedStatus = 'approved';
    protected $workflowRejectedStatus = 'rejected';
    protected $workflowAutoSubmit = false; // Manual submission
    
    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'po_number',
        'total_amount',
        'status',
        // ... other fields
    ];
}
```

## API Reference

### Workflow Submission

#### `submitToWorkflow($options = [])`

Starts an approval workflow for the document.

**Parameters:**
- `$options` (array): Optional configuration
  - `notes` (string): Workflow notes
  - `metadata` (array): Additional workflow metadata
  - `document_attributes` (array): Attributes for approval routing
    - `urgency`: 'normal', 'high', 'urgent'
    - `budget_type`: 'Operating', 'Capital', etc.
    - `transaction_category`: Custom categories
    - `is_budgeted`: true/false

**Returns:** `WorkflowInstance`

**Throws:** 
- `\Exception` if document type not defined
- `\Exception` if status not eligible
- `\Exception` if workflow already exists
- `\Exception` if no approval path found

**Example:**
```php
$workflow = $document->submitToWorkflow([
    'notes' => 'Emergency repair',
    'document_attributes' => [
        'urgency' => 'urgent',
        'budget_type' => 'Operating'
    ]
]);
```

---

#### `previewWorkflow($options = [])`

Previews the approval path without starting a workflow.

**Parameters:** Same as `submitToWorkflow()`

**Returns:** Array of approval steps with details

**Example:**
```php
$preview = $document->previewWorkflow();

foreach ($preview as $step) {
    echo "Step {$step['step']}: {$step['description']}\n";
    echo "  Required approvals: {$step['required_approvers']}\n";
    echo "  Estimated days: {$step['estimated_days']}\n";
}
```

---

### Workflow Actions

#### `approveWorkflow($options = [])`

Approves the current workflow step.

**Parameters:**
- `$options` (array):
  - `comments` (string): Optional approval comments
  - `metadata` (array): Additional metadata

**Returns:**
- `true` - Approval recorded, workflow continues
- `'completed'` - Workflow finished, document approved
- `string` - Error message if failed

**Example:**
```php
$result = $document->approveWorkflow([
    'comments' => 'Pricing verified and acceptable'
]);

switch ($result) {
    case true:
        // More approvals needed
        break;
    case 'completed':
        // Workflow done, document approved
        break;
    default:
        // Error occurred
        break;
}
```

---

#### `rejectWorkflow($options = [])`

Rejects the current workflow step.

**Parameters:**
- `$options` (array):
  - `comments` (string): **REQUIRED** - Rejection reason

**Returns:**
- `'rejected'` - Rejection successful
- `string` - Error message if failed

**Throws:** `ValidationException` if comments not provided

**Example:**
```php
$result = $document->rejectWorkflow([
    'comments' => 'Budget not available this quarter'
]);

if ($result === 'rejected') {
    // Document rejected, workflow ended
}
```

---

#### `cancelWorkflow($reason = null)`

Cancels the active workflow.

**Parameters:**
- `$reason` (string): Optional cancellation reason

**Returns:** `bool` - Success/failure

**Example:**
```php
if ($document->cancelWorkflow('Supplier no longer available')) {
    // Workflow cancelled, document status reset
}
```

---

### Workflow Status Queries

#### `getCurrentWorkflow()`

Gets the currently active workflow instance.

**Returns:** `WorkflowInstance|null`

**Example:**
```php
$workflow = $document->getCurrentWorkflow();

if ($workflow) {
    echo "Current step: {$workflow->current_step}\n";
    echo "Progress: {$workflow->steps_completed}/{$workflow->total_steps_required}\n";
}
```

---

#### `getCompletedWorkflow()`

Gets the most recent completed workflow.

**Returns:** `WorkflowInstance|null`

---

#### `hasActiveWorkflow()`

Checks if document has an active workflow.

**Returns:** `bool`

**Example:**
```php
if ($document->hasActiveWorkflow()) {
    // Cannot edit document
}
```

---

#### `isApproved()`

Checks if document has ever been fully approved.

**Returns:** `bool`

---

#### `isRejected()`

Checks if document has been rejected.

**Returns:** `bool`

---

#### `isWorkflowOverdue()`

Checks if current workflow step is past deadline.

**Returns:** `bool`

---

#### `isWorkflowEscalated()`

Checks if workflow has been escalated.

**Returns:** `bool`

---

### Workflow Progress

#### `getCurrentStep()`

Gets the current approval step name.

**Returns:** `string|null`

**Example:**
```php
echo "Awaiting: " . $document->getCurrentStep();
// Output: "Awaiting: Approval by Department Manager"
```

---

#### `getWorkflowProgress()`

Gets workflow completion percentage.

**Returns:** `float|null` - Percentage (0-100) or null if no workflow

**Example:**
```php
$progress = $document->getWorkflowProgress();
echo "Progress: {$progress}%";
// Output: "Progress: 66.67%"
```

---

#### `getWorkflowStatus()`

Gets human-readable workflow status.

**Returns:** `string`

**Possible Values:**
- "No Active Workflow"
- "Approved"
- "Pending Approval"
- "In Progress"
- "Overdue"
- "Escalated"

**Example:**
```php
$status = $document->getWorkflowStatus();
// Display badge color based on status
```

---

#### `getWorkflowHistory()`

Gets complete history of all workflow actions.

**Returns:** `Collection` of `WorkflowAction` models

**Example:**
```php
$history = $document->getWorkflowHistory();

foreach ($history as $action) {
    echo "{$action->staff->full_name} {$action->action}d ";
    echo "on {$action->action_taken_at->format('Y-m-d H:i')}\n";
    
    if ($action->comments) {
        echo "  Comment: {$action->comments}\n";
    }
}
```

---

## Automatic Behaviors

### Edit Protection

Documents with active workflows **cannot be modified** (except status changes).

```php
$document = PurchaseOrder::find(1);
$document->submitToWorkflow();

// This will throw ValidationException:
$document->total_amount = 5000;
$document->save();
// Error: "Document cannot be modified while approval is in progress."

// But status changes are allowed (for workflow progression):
$document->status = 'approved'; // OK
$document->save();
```

---

### Delete Protection

Documents with active workflows **cannot be deleted**.

```php
$document = PurchaseOrder::find(1);
$document->submitToWorkflow();

// This will throw ValidationException:
$document->delete();
// Error: "Document cannot be deleted while approval is in progress. Cancel the workflow first."

// Must cancel first:
$document->cancelWorkflow('Changed requirements');
$document->delete(); // Now OK
```

---

### Auto-Submit (Optional)

Enable automatic workflow submission on document creation.

```php
class PurchaseOrder extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    protected $workflowDocumentType = 'purchase_order';
    protected $workflowAutoSubmit = true; // Auto-submit on create
}

// Workflow starts automatically:
$po = PurchaseOrder::create([
    'po_number' => 'PO-001',
    'total_amount' => 5000,
    'status' => 'draft'
]);

// Workflow already started, status is now 'pending_approval'
```

---

## Integration with Views

### Display Workflow Status

```php
<!-- In your view partial -->
{% if record.hasActiveWorkflow() %}
    <div class="workflow-status">
        <span class="badge badge-warning">
            {{ record.getWorkflowStatus() }}
        </span>
        <div class="progress">
            <div class="progress-bar" style="width: {{ record.getWorkflowProgress() }}%">
                {{ record.getWorkflowProgress() }}%
            </div>
        </div>
        <p>Current step: {{ record.getCurrentStep() }}</p>
    </div>
{% endif %}
```

---

### Workflow History Widget

```php
<!-- Display approval history -->
<div class="workflow-history">
    <h4>Approval History</h4>
    {% set history = record.getWorkflowHistory() %}
    
    {% if history.count() %}
        <ul class="timeline">
            {% for action in history %}
                <li class="{{ action.action }}">
                    <strong>{{ action.staff.full_name }}</strong>
                    {{ action.action }}d on
                    {{ action.action_taken_at|date('Y-m-d H:i') }}
                    
                    {% if action.comments %}
                        <p class="comment">{{ action.comments }}</p>
                    {% endif %}
                </li>
            {% endfor %}
        </ul>
    {% else %}
        <p>No workflow history</p>
    {% endif %}
</div>
```

---

### Conditional Buttons

```php
<!-- Show approve/reject buttons only if applicable -->
{% if record.hasActiveWorkflow() %}
    <button 
        data-request="onApprove"
        data-request-data="id: {{ record.id }}"
        class="btn btn-success">
        Approve
    </button>
    
    <button 
        data-request="onReject"
        data-request-data="id: {{ record.id }}"
        class="btn btn-danger">
        Reject
    </button>
{% elseif record.isApproved() %}
    <span class="badge badge-success">Approved</span>
{% else %}
    <button 
        data-request="onSubmitForApproval"
        data-request-data="id: {{ record.id }}"
        class="btn btn-primary">
        Submit for Approval
    </button>
{% endif %}
```

---

## Advanced Usage

### Custom Workflow Attributes

Pass additional attributes for complex approval routing:

```php
$document->submitToWorkflow([
    'document_attributes' => [
        'urgency' => 'urgent',
        'budget_type' => 'Capital',
        'transaction_category' => 'Equipment',
        'is_budgeted' => true,
        'department' => 'IT',
        'project_id' => 123
    ]
]);
```

These attributes are passed to `ApprovalPathService` for intelligent routing.

---

### Conditional Auto-Submit

```php
class PurchaseOrder extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    protected $workflowDocumentType = 'purchase_order';
    
    // Override to add conditional logic
    protected function getWorkflowAutoSubmit()
    {
        // Only auto-submit if amount exceeds threshold
        return $this->total_amount > 10000;
    }
}
```

---

### Custom Status Mapping

```php
class StockAdjustment extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    
    protected $workflowDocumentType = 'stock_adjustment';
    
    // Map to your custom statuses
    protected $workflowEligibleStatuses = ['prepared', 'reviewed'];
    protected $workflowPendingStatus = 'awaiting_approval';
    protected $workflowApprovedStatus = 'approved_by_manager';
    protected $workflowRejectedStatus = 'rejected_by_manager';
}
```

---

## Troubleshooting

### Error: "Model must define $workflowDocumentType property"

**Solution:** Add the required property to your model:

```php
protected $workflowDocumentType = 'your_document_type';
```

---

### Error: "Document status 'X' is not eligible for workflow submission"

**Solution:** Either:
1. Change document status to eligible status first
2. Or add current status to `$workflowEligibleStatuses`:

```php
protected $workflowEligibleStatuses = ['draft', 'pending', 'reviewed'];
```

---

### Error: "Document already has an active workflow"

**Solution:** Cancel existing workflow before starting new one:

```php
if ($document->hasActiveWorkflow()) {
    $document->cancelWorkflow('Starting new workflow');
}
$document->submitToWorkflow();
```

---

### Error: "No approval path found"

**Solution:** Ensure approval rules are configured in Organization plugin for:
- The document type
- The document amount range
- The site/location
- The document attributes (budget type, category, etc.)

---

## Migration Guide

### Before (Manual Integration)

```php
// OLD WAY - Manual service instantiation
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Workflow\Services\WorkflowActionService;

class PurchaseOrderController extends Controller
{
    public function onSubmit()
    {
        $po = PurchaseOrder::find(post('id'));
        $workflowService = new WorkflowService();
        
        $workflow = $workflowService->startWorkflow($po, 'purchase_order');
        $po->status = 'pending_approval';
        $po->save();
    }
    
    public function onApprove()
    {
        $po = PurchaseOrder::find(post('id'));
        $workflow = WorkflowInstance::where('documentable_type', PurchaseOrder::class)
            ->where('documentable_id', $po->id)
            ->latest()
            ->first();
        
        $actionService = new WorkflowActionService();
        $result = $actionService->approve($workflow);
        
        if ($result === 'completed') {
            $po->status = 'approved';
            $po->save();
        }
    }
}
```

### After (Using Trait)

```php
// NEW WAY - Trait handles everything
class PurchaseOrder extends Model
{
    use \Omsb\Workflow\Traits\HasWorkflow;
    protected $workflowDocumentType = 'purchase_order';
}

class PurchaseOrderController extends Controller
{
    public function onSubmit()
    {
        $po = PurchaseOrder::find(post('id'));
        $po->submitToWorkflow();
    }
    
    public function onApprove()
    {
        $po = PurchaseOrder::find(post('id'));
        $po->approveWorkflow();
    }
}
```

**Benefits:**
- ✅ Less boilerplate code
- ✅ Consistent API across all models
- ✅ Automatic status management
- ✅ Built-in validations
- ✅ Protection against invalid operations

---

## Best Practices

1. **Always define `$workflowDocumentType`** - This is required and must match your approval rule configuration

2. **Configure status mappings** - Set `$workflowPendingStatus`, `$workflowApprovedStatus`, etc. to match your business logic

3. **Use `previewWorkflow()` for UX** - Show users the approval path before submission

4. **Provide rejection reasons** - Always include meaningful comments when rejecting

5. **Check workflow state** - Use `hasActiveWorkflow()` before allowing edits

6. **Display progress** - Use `getWorkflowProgress()` and `getCurrentStep()` in UI

7. **Log history** - Use `getWorkflowHistory()` for audit trails

8. **Handle exceptions** - Wrap workflow operations in try-catch blocks

---

## See Also

- [Workflow Plugin Main Documentation](../workflow.md)
- [WorkflowService API](../workflow.md#workflowservice)
- [WorkflowActionService API](../workflow.md#workflowactionservice)
- [Organization Plugin - Approval Rules](../organization.md#approval-model)
- [Workflow Quick Reference](../workflow-quick-reference.md)
