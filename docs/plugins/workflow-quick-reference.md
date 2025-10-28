# Workflow Plugin - Quick Reference

Quick reference guide for integrating the Workflow plugin into your OMSB plugin.

## Installation & Setup

### 1. Add Dependency

In your `Plugin.php`:
```php
public $require = ['Omsb.Workflow', 'Omsb.Organization'];
```

### 2. Add Approval Rules (Organization Plugin)

```php
use Omsb\Organization\Models\Approval;

Approval::create([
    'code' => 'YOUR_DOC_APPROVAL',
    'document_type' => 'your_document_type',
    'approval_type' => 'single', // or 'quorum', 'majority', 'unanimous'
    'required_approvers' => 1,
    'floor_limit' => 0,
    'ceiling_limit' => 100000,
    'approval_timeout_days' => 3,
    'is_active' => true
]);
```

## Common Usage Patterns

### Start a Workflow

```php
use Omsb\Workflow\Services\WorkflowService;

$workflowService = new WorkflowService();

$workflow = $workflowService->startWorkflow(
    $yourDocument,
    'your_document_type',
    ['notes' => 'Optional workflow notes']
);

// Result: WorkflowInstance object
echo $workflow->workflow_code; // "WF-XXX-202401-00123"
```

### Preview Approval Path

```php
$preview = $workflowService->previewWorkflow(
    $yourDocument,
    'your_document_type'
);

// Result: Array of approval steps
foreach ($preview as $step) {
    echo "Step {$step['step']}: {$step['description']}\n";
}
```

### Record Approval

```php
use Omsb\Workflow\Services\WorkflowActionService;
use Omsb\Workflow\Models\WorkflowInstance;

$actionService = new WorkflowActionService();

// Get workflow
$workflow = WorkflowInstance::where('documentable_type', YourModel::class)
    ->where('documentable_id', $yourDocument->id)
    ->where('status', 'pending')
    ->first();

// Approve
$result = $actionService->approve($workflow, [
    'comments' => 'Optional comments'
]);

if ($result === 'completed') {
    // All approvals received!
    $yourDocument->update(['status' => 'approved']);
} elseif ($result === true) {
    // Approval recorded, more needed
} else {
    // Error: $result contains error message
}
```

### Record Rejection

```php
$result = $actionService->reject($workflow, [
    'comments' => 'Required rejection reason'
]);

if ($result === 'rejected') {
    $yourDocument->update(['status' => 'rejected']);
}
```

### Check Workflow Status

```php
// Get current workflow
$workflow = $yourDocument->workflows()
    ->whereIn('status', ['pending', 'in_progress'])
    ->latest()
    ->first();

// Check completion
if ($workflow && $workflow->isComplete()) {
    echo "Workflow completed!";
}

// Get progress
$percentage = $workflow->getCompletionPercentage();
echo "Progress: {$percentage}%";

// Check if overdue
if ($workflow->is_overdue) {
    echo "Warning: Workflow is overdue!";
}
```

### Get Workflow History

```php
$history = $actionService->getWorkflowHistory($workflow);

foreach ($history as $action) {
    echo "{$action->staff->full_name} {$action->action}d ";
    echo "on {$action->action_taken_at->format('Y-m-d H:i')}\n";
    if ($action->comments) {
        echo "  Comments: {$action->comments}\n";
    }
}
```

## Model Integration

### Add Workflow Relationship

```php
namespace YourPlugin\Models;

class YourDocument extends Model
{
    // Add morphMany relationship
    public $morphMany = [
        'workflows' => [
            \Omsb\Workflow\Models\WorkflowInstance::class,
            'name' => 'documentable'
        ]
    ];
    
    // Helper method to start workflow
    public function submitForApproval()
    {
        $workflowService = new \Omsb\Workflow\Services\WorkflowService();
        return $workflowService->startWorkflow($this, 'your_document_type');
    }
    
    // Helper to check approval status
    public function isApproved()
    {
        return $this->workflows()
            ->where('status', 'completed')
            ->exists();
    }
    
    // Get current workflow
    public function getCurrentWorkflow()
    {
        return $this->workflows()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();
    }
}
```

## Controller Integration

### Basic Controller Setup

```php
namespace YourPlugin\Controllers;

use Backend\Classes\Controller;
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Workflow\Services\WorkflowActionService;

class YourDocuments extends Controller
{
    protected $workflowService;
    protected $actionService;
    
    public function __construct()
    {
        parent::__construct();
        $this->workflowService = new WorkflowService();
        $this->actionService = new WorkflowActionService();
    }
    
    // Submit document for approval
    public function onSubmitForApproval()
    {
        $document = YourDocument::find(post('id'));
        
        try {
            $workflow = $this->workflowService->startWorkflow(
                $document, 
                'your_document_type'
            );
            
            Flash::success("Document submitted for approval. Code: {$workflow->workflow_code}");
        } catch (\Exception $e) {
            Flash::error("Submission failed: " . $e->getMessage());
        }
    }
    
    // Approve document
    public function onApprove()
    {
        $document = YourDocument::find(post('id'));
        $workflow = $document->getCurrentWorkflow();
        
        if (!$workflow) {
            Flash::error('No active workflow found');
            return;
        }
        
        $result = $this->actionService->approve($workflow, [
            'comments' => post('comments')
        ]);
        
        if ($result === 'completed') {
            $document->update(['status' => 'approved']);
            Flash::success('Document approved!');
        } elseif ($result === true) {
            Flash::success('Approval recorded. Awaiting additional approvals.');
        } else {
            Flash::error("Approval failed: {$result}");
        }
    }
    
    // Reject document
    public function onReject()
    {
        $document = YourDocument::find(post('id'));
        $workflow = $document->getCurrentWorkflow();
        
        if (!$workflow) {
            Flash::error('No active workflow found');
            return;
        }
        
        $result = $this->actionService->reject($workflow, [
            'comments' => post('rejection_reason')
        ]);
        
        if ($result === 'rejected') {
            $document->update(['status' => 'rejected']);
            Flash::warning('Document rejected');
        } else {
            Flash::error("Rejection failed: {$result}");
        }
    }
}
```

## Approval Types

### Single Approver

One specific person must approve:

```php
Approval::create([
    'approval_type' => 'single',
    'staff_id' => $manager->id,
    'required_approvers' => 1
]);
```

### Quorum (X out of Y)

Requires X approvals from a pool of Y eligible approvers:

```php
Approval::create([
    'approval_type' => 'quorum',
    'required_approvers' => 3,          // Need 3 approvals
    'eligible_approvers' => 5,          // From 5 eligible approvers
    'eligible_staff_ids' => [1,2,3,4,5] // The 5 eligible staff
]);
```

### Majority

More than half must approve:

```php
Approval::create([
    'approval_type' => 'majority',
    'eligible_approvers' => 5,
    'required_approvers' => 3 // Calculated as (5/2)+1
]);
```

### Unanimous

All must approve:

```php
Approval::create([
    'approval_type' => 'unanimous',
    'eligible_approvers' => 3,
    'required_approvers' => 3
]);
```

## Scheduled Tasks

### Handle Overdue Workflows

In your `Plugin.php`:

```php
public function registerSchedule($schedule)
{
    $schedule->call(function () {
        $this->handleOverdueWorkflows();
    })->daily();
}

protected function handleOverdueWorkflows()
{
    $actionService = new \Omsb\Workflow\Services\WorkflowActionService();
    
    $overdueWorkflows = \Omsb\Workflow\Models\WorkflowInstance::where('status', 'pending')
        ->where('due_at', '<', now())
        ->where('is_overdue', false)
        ->get();
    
    foreach ($overdueWorkflows as $workflow) {
        $workflow->update(['is_overdue' => true]);
        $actionService->handleOverdueWorkflow($workflow);
    }
}
```

## Testing

### Basic Workflow Test

```php
namespace YourPlugin\Tests;

use PluginTestCase;
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Workflow\Services\WorkflowActionService;

class WorkflowTest extends PluginTestCase
{
    public function testWorkflowCreation()
    {
        $document = YourDocument::create([...]);
        
        $workflowService = new WorkflowService();
        $workflow = $workflowService->startWorkflow($document, 'your_doc_type');
        
        $this->assertNotNull($workflow);
        $this->assertEquals('pending', $workflow->status);
        $this->assertEquals($document->id, $workflow->documentable_id);
    }
    
    public function testApprovalProcess()
    {
        $workflow = WorkflowInstance::factory()->create();
        $actionService = new WorkflowActionService();
        
        $result = $actionService->approve($workflow);
        
        $this->assertTrue($result === true || $result === 'completed');
    }
}
```

## Common Pitfalls

### ❌ Don't Create Approval Rules in Workflow Plugin

```php
// WRONG - Don't do this
WorkflowInstance::create([...approval rule data...]);
```

```php
// CORRECT - Create approval rules in Organization plugin
Omsb\Organization\Models\Approval::create([...]);
```

### ❌ Don't Start Multiple Workflows for Same Document

```php
// WRONG
$workflow1 = $service->startWorkflow($doc, 'type');
$workflow2 = $service->startWorkflow($doc, 'type'); // Duplicate!
```

```php
// CORRECT - Check for existing workflow first
$existing = WorkflowInstance::where('documentable_type', get_class($doc))
    ->where('documentable_id', $doc->id)
    ->whereIn('status', ['pending', 'in_progress'])
    ->first();

if (!$existing) {
    $workflow = $service->startWorkflow($doc, 'type');
}
```

### ❌ Don't Update Document Status Before Workflow Completes

```php
// WRONG
$service->approve($workflow);
$document->update(['status' => 'approved']); // Too early!
```

```php
// CORRECT
$result = $service->approve($workflow);
if ($result === 'completed') {
    $document->update(['status' => 'approved']);
}
```

## Troubleshooting

### "No approval path found" Error

**Cause**: No approval rules match the document criteria

**Solution**: 
1. Check if approval rules exist in Organization.Approval
2. Verify document type matches rule's `document_type`
3. Check amount is within rule's floor/ceiling limits
4. Ensure rule is active and effective dates are valid

### "User not authorized to approve"

**Cause**: Current user doesn't have approval authority

**Solution**:
1. Check user is assigned to correct Staff record
2. Verify approval rule references correct staff/position
3. For quorum rules, ensure user is in `eligible_staff_ids`

### Workflow Not Progressing

**Cause**: Insufficient approvals received

**Solution**:
1. Check `approvals_received` vs `approvals_required`
2. Verify approval type (quorum needs multiple approvers)
3. Ensure approvers haven't already acted on this step

## Additional Resources

- [Full Documentation](workflow.md) - Complete plugin documentation
- [Organization Plugin](../organization/README.md) - Approval rule setup
- [OctoberCMS Docs](https://docs.octobercms.com/4.x/) - Framework documentation

---

**Quick Reference Version**: 1.0.0  
**Last Updated**: January 2024
