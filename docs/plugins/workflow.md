# Workflow Plugin Documentation

## Table of Contents

1. [Overview](#overview)
2. [Plugin Purpose](#plugin-purpose)
3. [Core Capabilities](#core-capabilities)
4. [Architecture](#architecture)
5. [Models](#models)
6. [Services](#services)
7. [Integration Points](#integration-points)
8. [API Reference](#api-reference)
9. [Usage Examples](#usage-examples)
10. [Future Improvements](#future-improvements)

---

## Overview

The **Workflow Plugin** (`Omsb\Workflow`) is a specialized plugin focused on **workflow execution and tracking** for approval processes across the OMSB system. It orchestrates multi-level approval workflows by managing ongoing workflow instances and recording individual approval actions.

**Key Philosophy**: The Workflow plugin **does NOT define approval rules**. Instead, it **executes and tracks** approval workflows based on rules defined in the Organization plugin.

### Version
- **Current Version**: 1.0.0
- **Namespace**: `Omsb\Workflow`
- **Dependencies**: `Omsb.Organization` (required)

---

## Plugin Purpose

### What It Does
The Workflow plugin serves as the **execution engine** for approval processes throughout the OMSB system:

1. **Tracks Ongoing Workflows**: Creates and manages workflow instances for documents requiring approval
2. **Records Approval Actions**: Logs every approval, rejection, delegation, and escalation action
3. **Manages Progress**: Monitors workflow completion status and step progression
4. **Handles Timeouts**: Automatically detects overdue approvals and triggers escalations
5. **Provides Audit Trail**: Maintains comprehensive history of all workflow activities

### What It Does NOT Do
- ❌ Define WHO can approve documents
- ❌ Set approval amount limits
- ❌ Configure approval hierarchies
- ❌ Manage organizational structure

**These responsibilities belong to the Organization plugin.**

---

## Core Capabilities

### 1. Automatic Approval Path Determination
Automatically determines the sequence of approval steps required for any document based on:
- Document type (Purchase Order, Stock Adjustment, etc.)
- Document amount
- Site context
- Budget type
- Transaction attributes

### 2. Multiple Approval Types Support
- **Single Approver**: One designated person must approve
- **Quorum-Based**: X out of Y approvers must approve (e.g., "3 of 5 department heads")
- **Majority**: More than half of eligible approvers must approve
- **Unanimous**: All eligible approvers must approve

### 3. Workflow Instance Management
- Creates unique workflow instances for each approval process
- Tracks current step, progress percentage, and completion status
- Maintains approval counts (required vs. received)
- Manages workflow lifecycle from initiation to completion

### 4. Action Recording and Tracking
- Records every individual approval or rejection action
- Captures approver comments and reasoning
- Tracks delegation chains
- Timestamps all actions for audit purposes

### 5. Timeout and Escalation Handling
- Detects overdue approvals based on configured timeout periods
- Automatically escalates to higher authority when configured
- Supports auto-rejection on timeout if specified
- Logs all escalation actions

### 6. Comprehensive Audit Trail
- Complete history of all workflow actions
- User, timestamp, and IP address tracking
- Comment and reasoning preservation
- Enables compliance and performance analysis

---

## Architecture

### Separation of Concerns

The Workflow plugin follows a clear architectural separation with the Organization plugin:

| **Organization Plugin** | **Workflow Plugin** |
|-------------------------|---------------------|
| **Approval Definitions** | **Workflow Execution** |
| Defines WHO can approve | Tracks ONGOING approvals |
| Sets HOW MUCH can be approved | Records individual ACTIONS |
| Specifies WHICH documents | Monitors progress |
| Configures approval POLICIES | Manages overdue workflows |
| Manages hierarchy rules | Maintains audit trail |

### Data Flow

```
Document Creation
    ↓
WorkflowService.startWorkflow()
    ↓
ApprovalPathService.determineApprovalPath()
    ├── Queries Organization.Approval rules
    ├── Filters by amount, site, document type
    └── Returns sequence of approval rule IDs
    ↓
Create WorkflowInstance
    ├── Sets current_step
    ├── Sets approvals_required
    └── Links to document (morphTo)
    ↓
Approver Actions
    ├── WorkflowActionService.approve()
    ├── WorkflowActionService.reject()
    └── Creates WorkflowAction records
    ↓
Progress Checking
    ├── hasSufficientApprovals()?
    ├── YES → advanceToNextStep() or completeWorkflow()
    └── NO → Continue waiting
    ↓
Workflow Completion
    └── Update document status to 'approved'
```

### Plugin Structure

```
plugins/omsb/workflow/
├── Plugin.php                      # Plugin registration
├── models/
│   ├── WorkflowInstance.php        # Workflow instance model
│   ├── WorkflowAction.php          # Approval action model
│   ├── workflowinstance/           # Form/column definitions
│   └── workflowaction/             # Form/column definitions
├── services/
│   ├── WorkflowService.php         # Main workflow operations
│   ├── WorkflowActionService.php   # Approval/rejection handling
│   └── ApprovalPathService.php     # Path determination logic
├── updates/
│   ├── create_workflow_instances_table.php
│   ├── create_workflow_actions_table.php
│   └── version.yaml
├── spec.yaml                       # Plugin specification (legacy)
├── README.md                       # Basic plugin documentation
├── WORKFLOW_EXAMPLE.md             # Purchase Order example
└── docs/                           # This documentation folder
```

---

## Models

### WorkflowInstance Model

**Purpose**: Represents an ongoing approval workflow instance for a specific document.

**Namespace**: `Omsb\Workflow\Models\WorkflowInstance`

**Table**: `omsb_workflow_instances`

#### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `workflow_code` | string | Unique identifier (e.g., "WF-PUR-202401-00123") |
| `status` | enum | pending, in_progress, completed, failed, cancelled |
| `document_type` | string | Type of document (e.g., 'purchase_order') |
| `documentable_type` | string | Polymorphic - document class name |
| `documentable_id` | bigint | Polymorphic - document ID |
| `document_amount` | decimal | Amount for approval routing |
| `current_step` | string | Human-readable current step name |
| `total_steps_required` | integer | Total approval steps in path |
| `steps_completed` | integer | Number of steps completed so far |
| `approval_path` | json | Array of approval rule IDs in sequence |
| `current_approval_rule_id` | bigint | FK to Organization.Approval |
| `current_approval_type` | enum | single, quorum, majority, unanimous |
| `approvals_required` | integer | Approvals needed for current step |
| `approvals_received` | integer | Approvals received for current step |
| `rejections_received` | integer | Rejection count |
| `started_at` | timestamp | When workflow started |
| `completed_at` | timestamp | When workflow completed |
| `due_at` | timestamp | Deadline for current step |
| `is_overdue` | boolean | Whether current step is overdue |
| `is_escalated` | boolean | Whether workflow has been escalated |
| `escalated_at` | timestamp | When escalation occurred |
| `escalation_reason` | text | Reason for escalation |
| `workflow_notes` | text | Additional notes |
| `metadata` | json | Additional workflow data |
| `site_id` | bigint | FK to Organization.Site |
| `created_by` | integer | FK to backend_users |

#### Relationships

```php
// Belongs To
'current_approval_rule' => [\Omsb\Organization\Models\Approval::class]
'site' => [\Omsb\Organization\Models\Site::class]
'created_by_user' => [\Backend\Models\User::class]

// Has Many
'workflow_actions' => [WorkflowAction::class]
'approvals' => [WorkflowAction::class, conditions: "action = 'approve'"]
'rejections' => [WorkflowAction::class, conditions: "action = 'reject'"]

// Morph To
'documentable' => [] // Links to any document (PurchaseOrder, StockAdjustment, etc.)
```

#### Scopes

```php
WorkflowInstance::pending()      // status = 'pending'
WorkflowInstance::inProgress()   // status = 'in_progress'
WorkflowInstance::completed()    // status = 'completed'
WorkflowInstance::overdue()      // is_overdue = true
WorkflowInstance::escalated()    // is_escalated = true
```

#### Methods

```php
// Check if workflow is complete
$workflow->isComplete(): bool

// Check if current step has sufficient approvals
$workflow->hasSufficientApprovals(): bool

// Get completion percentage (0-100)
$workflow->getCompletionPercentage(): float

// Get human-readable progress status
$workflow->getProgressStatus(): string // "Completed", "Overdue", "Escalated", etc.
```

---

### WorkflowAction Model

**Purpose**: Records individual approval, rejection, or other actions taken on a workflow.

**Namespace**: `Omsb\Workflow\Models\WorkflowAction`

**Table**: `omsb_workflow_actions`

#### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `workflow_instance_id` | bigint | FK to WorkflowInstance (cascades) |
| `approval_rule_id` | bigint | FK to Organization.Approval |
| `staff_id` | bigint | FK to Organization.Staff who took action |
| `user_id` | integer | FK to backend_users |
| `action` | enum | approve, reject, delegate, escalate, comment |
| `step_name` | string | Name/description of approval step |
| `step_sequence` | integer | Order in workflow (1, 2, 3, ...) |
| `comments` | text | Approver comments |
| `rejection_reason` | text | Specific rejection reason |
| `is_automatic` | boolean | Whether action was automated |
| `is_delegated_action` | boolean | Whether this was delegated |
| `delegation_reason` | text | Reason for delegation |
| `original_staff_id` | bigint | FK to original staff (if delegated) |
| `action_taken_at` | timestamp | When action was performed |
| `due_at` | timestamp | When step was due |
| `is_overdue_action` | boolean | Whether action was taken after deadline |

#### Relationships

```php
// Belongs To
'workflow_instance' => [WorkflowInstance::class]
'approval_rule' => [\Omsb\Organization\Models\Approval::class]
'staff' => [\Omsb\Organization\Models\Staff::class]
'original_staff' => [\Omsb\Organization\Models\Staff::class]
'user' => [\Backend\Models\User::class]
```

#### Scopes

```php
WorkflowAction::approved()     // action = 'approve'
WorkflowAction::rejected()     // action = 'reject'
WorkflowAction::delegated()    // is_delegated_action = true
WorkflowAction::overdue()      // is_overdue_action = true
```

---

## Services

### WorkflowService

**Purpose**: Main service for creating and managing workflow instances. Automatically determines approval paths.

**Namespace**: `Omsb\Workflow\Services\WorkflowService`

#### Primary Methods

##### `startWorkflow($document, $documentType, $options = [])`

Starts an approval workflow for any document.

**Parameters**:
- `$document` (mixed): The document object needing approval
- `$documentType` (string): Type identifier (e.g., 'purchase_order', 'stock_adjustment')
- `$options` (array): Optional configuration
  - `notes` (string): Workflow notes
  - `metadata` (array): Additional workflow data
  - `document_attributes` (array): Attributes for approval routing

**Returns**: `WorkflowInstance`

**Throws**: `\Exception` if no approval path found

**Example**:
```php
use Omsb\Feeder\Models\Feed;

$workflowService = new WorkflowService();

$workflow = $workflowService->startWorkflow($purchaseOrder, 'purchase_order', [
    'notes' => 'Urgent equipment purchase',
    'document_attributes' => [
        'urgency' => 'high',
        'budget_type' => 'Capital',
        'transaction_category' => 'Equipment'
    ]
]);

echo "Workflow {$workflow->workflow_code} created";
```

##### `startPurchaseOrderWorkflow($purchaseOrder, $options = [])`

Convenience method for Purchase Order workflows.

**Parameters**:
- `$purchaseOrder`: PurchaseOrder model instance
- `$options`: Same as `startWorkflow()`

**Returns**: `WorkflowInstance`

##### `startStockAdjustmentWorkflow($stockAdjustment, $options = [])`

Convenience method for Stock Adjustment workflows.

**Parameters**:
- `$stockAdjustment`: StockAdjustment model instance
- `$options`: Same as `startWorkflow()`

**Returns**: `WorkflowInstance`

##### `previewWorkflow($document, $documentType, $options = [])`

Previews the approval path without creating a workflow instance.

**Parameters**: Same as `startWorkflow()`

**Returns**: Array of approval steps with details

**Example**:
```php
$preview = $workflowService->previewWorkflow($purchaseRequest, 'purchase_request');

foreach ($preview as $step) {
    echo "Step {$step['step']}: {$step['description']}\n";
    echo "  Type: {$step['approval_type']}\n";
    echo "  Required: {$step['required_approvers']} approvals\n";
    echo "  Estimated: {$step['estimated_days']} days\n";
}
```

#### Protected Helper Methods

These methods are used internally but documented for understanding:

- `determineApprovalPath()`: Queries Organization approval rules to build path
- `generateWorkflowCode()`: Creates unique workflow identifier
- `getStepName()`: Generates human-readable step name
- `calculateDueDate()`: Calculates deadline based on approval rule timeout
- `updateDocumentStatus()`: Updates source document status
- `notifyApprovers()`: Triggers notifications (placeholder for future implementation)

---

### WorkflowActionService

**Purpose**: Handles approval and rejection actions on workflow instances. Manages step progression and completion.

**Namespace**: `Omsb\Workflow\Services\WorkflowActionService`

#### Primary Methods

##### `approve(WorkflowInstance $workflow, $options = [])`

Records an approval action for the current workflow step.

**Parameters**:
- `$workflow`: WorkflowInstance being acted upon
- `$options` (array):
  - `comments` (string): Optional approval comments
  - `metadata` (array): Additional action metadata

**Returns**: 
- `true` if approval recorded (workflow continues)
- `'completed'` if workflow finished
- Error message (string) if action failed

**Authentication**: Uses `BackendAuth::getUser()` for current user

**Validations**:
- Workflow must be in 'pending' status
- User must have approval authority for current step
- User cannot approve twice on same step

**Example**:
```php
use Omsb\Organization\Models\Staff;

$actionService = new WorkflowActionService();

$result = $actionService->approve($workflow, [
    'comments' => 'Approved - pricing is competitive'
]);

if ($result === true) {
    Flash::success('Approval recorded. Waiting for additional approvals.');
} elseif ($result === 'completed') {
    Flash::success('All approvals received! Workflow completed.');
} else {
    Flash::error("Approval failed: {$result}");
}
```

##### `reject(WorkflowInstance $workflow, $options = [])`

Records a rejection action for the current workflow step.

**Parameters**:
- `$workflow`: WorkflowInstance being rejected
- `$options` (array):
  - `comments` (string): **Mandatory** rejection reason
  - `metadata` (array): Additional action metadata

**Returns**: 
- `'rejected'` if rejection successful
- Error message (string) if action failed

**Side Effects**:
- Workflow status set to 'rejected'
- Document status updated to 'rejected'
- Workflow cannot proceed further

**Example**:
```php
$result = $actionService->reject($workflow, [
    'comments' => 'Budget allocation insufficient for this quarter'
]);

if ($result === 'rejected') {
    Flash::warning('Purchase Order rejected and returned to creator.');
} else {
    Flash::error("Rejection failed: {$result}");
}
```

##### `getWorkflowHistory(WorkflowInstance $workflow)`

Retrieves complete history of all actions taken on a workflow.

**Parameters**:
- `$workflow`: WorkflowInstance to get history for

**Returns**: Collection of `WorkflowAction` models with relationships loaded

**Example**:
```php
$history = $actionService->getWorkflowHistory($workflow);

foreach ($history as $action) {
    echo "{$action->staff->full_name} {$action->action}d on {$action->action_taken_at}\n";
    if ($action->comments) {
        echo "  Comment: {$action->comments}\n";
    }
}
```

##### `isWorkflowOverdue(WorkflowInstance $workflow)`

Checks if the current workflow step is past its deadline.

**Parameters**:
- `$workflow`: WorkflowInstance to check

**Returns**: `bool`

**Example**:
```php
if ($actionService->isWorkflowOverdue($workflow)) {
    echo "Warning: This workflow is overdue by " . 
         $workflow->due_at->diffForHumans() . "\n";
}
```

##### `handleOverdueWorkflow(WorkflowInstance $workflow, $options = [])`

Handles overdue workflow according to configured timeout action.

**Parameters**:
- `$workflow`: Overdue WorkflowInstance
- `$options`: Additional handling options

**Returns**: `bool` - success/failure

**Actions Taken** (based on approval rule configuration):
- **Escalate**: Routes to higher authority (if `escalation_enabled`)
- **Auto-reject**: Automatically rejects workflow (if `auto_reject_on_timeout`)
- **Do nothing**: Returns false

**Example**:
```php
// Typically called by scheduled task
$overdueWorkflows = WorkflowInstance::overdue()->get();

foreach ($overdueWorkflows as $workflow) {
    $actionService->handleOverdueWorkflow($workflow);
}
```

#### Protected Helper Methods

- `canUserApprove()`: Validates user authorization for current step
- `hasUserActedOnCurrentStep()`: Prevents duplicate actions
- `isCurrentStepSatisfied()`: Checks if approval requirements met
- `advanceToNextStep()`: Moves workflow to next approval step
- `completeWorkflow()`: Marks workflow as completed
- `escalateWorkflow()`: Handles escalation logic
- `userHoldsPosition()`: Validates position-based approval authority

---

### ApprovalPathService

**Purpose**: Determines the sequence of approval rules required for a document based on business criteria.

**Namespace**: `Omsb\Workflow\Services\ApprovalPathService`

#### Primary Methods

##### `determineApprovalPath($documentType, $amount, $siteId, $documentAttributes = [])`

Determines the complete approval path for a document.

**Parameters**:
- `$documentType` (string): Type of document (e.g., 'purchase_order')
- `$amount` (float): Document amount for routing
- `$siteId` (int): Site ID for location-based rules
- `$documentAttributes` (array): Additional routing criteria
  - `current_status`: Document's current status
  - `budget_type`: Operating, Capital, etc.
  - `transaction_category`: Equipment, Services, etc.
  - `is_budgeted`: Whether expense is budgeted
  - `urgency`: normal, high, urgent
  - `created_by`: Creator staff ID

**Returns**: Array of approval rule IDs in sequence

**Algorithm**:
1. Query applicable approval rules from Organization.Approval
2. Filter by document type, site, effective dates
3. Apply amount-based filtering (floor_limit, ceiling_limit)
4. Apply budget type and category filters
5. Sort by floor_limit (ascending hierarchy)
6. Build sequential path of rule IDs

**Example**:
```php
use Omsb\Organization\Models\Approval;

$pathService = new ApprovalPathService();

$path = $pathService->determineApprovalPath(
    'purchase_order',
    25000.00,
    $siteId = 1,
    [
        'current_status' => 'draft',
        'budget_type' => 'Operating',
        'transaction_category' => 'Equipment',
        'is_budgeted' => true,
        'urgency' => 'normal'
    ]
);

// Returns: [12, 45, 78] - IDs of approval rules in sequence
```

##### `previewApprovalPath($documentType, $amount, $siteId, $documentAttributes = [])`

Generates a human-readable preview of the approval path.

**Parameters**: Same as `determineApprovalPath()`

**Returns**: Array of step details with:
- `step`: Step number
- `rule_code`: Approval rule code
- `approval_type`: single, quorum, etc.
- `required_approvers`: Number needed
- `eligible_approvers`: Pool size
- `description`: Human-readable description
- `estimated_days`: Timeout period

**Example**:
```php
$preview = $pathService->previewApprovalPath('purchase_request', 15000, 1);

// Returns:
[
    [
        'step' => 1,
        'rule_code' => 'PR_MGR_APPROVAL',
        'approval_type' => 'single',
        'required_approvers' => 1,
        'description' => 'Requires approval from John Smith',
        'estimated_days' => 3
    ],
    [
        'step' => 2,
        'rule_code' => 'PR_DEPT_HEAD',
        'approval_type' => 'quorum',
        'required_approvers' => 2,
        'eligible_approvers' => 3,
        'description' => 'Requires 2 out of 3 approvals',
        'estimated_days' => 5
    ]
]
```

##### `createPathForPurchaseOrder($purchaseOrder)`

Convenience method for Purchase Order path determination.

**Parameters**:
- `$purchaseOrder`: PurchaseOrder model instance

**Returns**: Array of approval rule IDs

##### `createPathForStockAdjustment($stockAdjustment)`

Convenience method for Stock Adjustment path determination.

**Parameters**:
- `$stockAdjustment`: StockAdjustment model instance

**Returns**: Array of approval rule IDs

**Special Logic**: Uses absolute value of adjustment amount for routing

#### Protected Helper Methods

- `getApplicableRules()`: Queries and filters approval rules
- `ruleApplies()`: Validates if specific rule applies to context
- `getNextMandatoryRule()`: Finds subsequent required approval step
- `getApprovalDescription()`: Generates human-readable rule description

---

## Integration Points

### With Organization Plugin

**Dependency**: `Omsb.Organization` (required in Plugin.php)

#### Approval Rules (`Organization\Approval` Model)

The Workflow plugin queries the Organization plugin's Approval model to determine approval paths:

```php
use Omsb\Organization\Models\Approval;

// Get applicable approval rules
$rules = Approval::where('document_type', 'purchase_order')
    ->where('is_active', true)
    ->where('amount_min', '<=', $amount)
    ->where('amount_max', '>=', $amount)
    ->get();
```

**Key Fields Used**:
- `document_type`: Matches workflow document type
- `floor_limit`, `ceiling_limit`: Amount-based routing
- `approval_type`: single, quorum, majority, unanimous
- `required_approvers`, `eligible_approvers`: Approval counts
- `approval_timeout_days`: Deadline calculation
- `from_status`, `to_status`: Status transitions
- `escalation_approval_rule_id`: Escalation routing

#### Staff Hierarchy

Workflow validates approvers against organizational hierarchy:

```php
use Omsb\Procurement\Models\PurchaseOrder;

// Check if approver is in hierarchy above creator
$creator = Staff::find($document->created_by);
$approver = Staff::find($currentUser->staff_id);

// Validation: approver must be senior to creator
if (!$approver->isSuperiorTo($creator)) {
    throw new \Exception("Approver must be in hierarchy above document creator");
}
```

#### Site Context

Workflows respect site-based approval rules:

```php
// Site-specific approval rules
Approval::where('site_id', $document->site_id)
    ->orWhereNull('site_id') // Global rules
    ->get();
```

---

### With Procurement Plugin

**Documents Supported**:
- Purchase Request (`purchase_request`)
- Purchase Order (`purchase_order`)
- Vendor Quotation (`vendor_quotation`)
- Goods Receipt Note (`goods_receipt_note`)
- Delivery Order (`delivery_order`)

#### Usage Example

```php
// In PurchaseOrderController
use Omsb\Workflow\Models\WorkflowInstance;

public function onApprove()
{
    $po = PurchaseOrder::find(post('id'));
    
    // Start workflow
    $workflowService = new WorkflowService();
    $workflow = $workflowService->startPurchaseOrderWorkflow($po);
    
    // Update PO status to indicate approval pending
    $po->update(['status' => 'pending_approval']);
    
    return ['workflow_code' => $workflow->workflow_code];
}

public function onCheckApprovalStatus()
{
    $po = PurchaseOrder::find(post('id'));
    
    // Get workflow instance
    $workflow = WorkflowInstance::where('documentable_type', PurchaseOrder::class)
        ->where('documentable_id', $po->id)
        ->latest()
        ->first();
    
    if ($workflow && $workflow->isComplete()) {
        return ['status' => 'approved', 'completion_date' => $workflow->completed_at];
    }
    
    return ['status' => 'pending', 'progress' => $workflow->getCompletionPercentage()];
}
```

---

### With Inventory Plugin

**Documents Supported**:
- Material Request Issuance (`material_request_issuance`)
- Stock Adjustment (`stock_adjustment`)
- Stock Transfer (`stock_transfer`)
- Physical Count (`physical_count`)

#### Usage Example

```php
// In StockAdjustmentController
use Omsb\Workflow\Services\WorkflowService;

public function onSubmitForApproval()
{
    $adjustment = StockAdjustment::find(post('id'));
    
    // Calculate adjustment value for approval routing
    $adjustmentValue = abs($adjustment->total_value);
    
    // Start workflow
    $workflowService = new WorkflowService();
    $workflow = $workflowService->startStockAdjustmentWorkflow($adjustment, [
        'document_attributes' => [
            'adjustment_type' => $adjustment->adjustment_type,
            'has_discrepancy' => $adjustment->has_discrepancy
        ]
    ]);
    
    return ['success' => true, 'workflow_code' => $workflow->workflow_code];
}
```

---

### With Registrar Plugin

**Integration**: Document status synchronization

The Workflow plugin updates document status fields managed by the Registrar plugin:

```php
// When workflow starts
$document->update(['status' => 'pending_approval']);

// When workflow completes
$document->update(['status' => 'approved']);

// When workflow is rejected
$document->update(['status' => 'rejected']);
```

---

### With Feeder Plugin

**Integration**: Activity tracking

All workflow actions should be logged to the Feeder plugin for activity feeds:

```php
use Omsb\Workflow\Services\WorkflowService;

// After approval action
Feed::create([
    'user_id' => $approver->id,
    'action_type' => 'approve',
    'feedable_type' => WorkflowInstance::class,
    'feedable_id' => $workflow->id,
    'additional_data' => [
        'document_type' => $workflow->document_type,
        'document_amount' => $workflow->document_amount,
        'workflow_code' => $workflow->workflow_code
    ]
]);
```

**Note**: Current implementation has placeholder for Feeder integration. Full integration pending.

---

## API Reference

### Public Endpoints for Other Plugins

The Workflow plugin exposes these public APIs for integration:

#### 1. Start Workflow

```php
use Omsb\Workflow\Services\WorkflowService;

$workflowService = new WorkflowService();

// Generic workflow start
$workflow = $workflowService->startWorkflow(
    $document,           // Your document model
    'document_type',     // String identifier
    $options            // Optional configuration
);

// Specialized methods
$workflow = $workflowService->startPurchaseOrderWorkflow($purchaseOrder, $options);
$workflow = $workflowService->startStockAdjustmentWorkflow($stockAdjustment, $options);
```

#### 2. Preview Approval Path

```php
// Preview without starting workflow
$preview = $workflowService->previewWorkflow($document, 'document_type', $options);

// Returns array of steps with details
foreach ($preview as $step) {
    echo "Step {$step['step']}: {$step['description']}\n";
}
```

#### 3. Record Approval

```php
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Workflow\Services\WorkflowService;

$actionService = new WorkflowActionService();

// Get workflow instance for your document
$workflow = WorkflowInstance::where('documentable_type', YourModel::class)
    ->where('documentable_id', $yourDocument->id)
    ->where('status', 'pending')
    ->first();

// Record approval
$result = $actionService->approve($workflow, [
    'comments' => 'Optional approval comments'
]);

// Handle result
if ($result === 'completed') {
    // Workflow finished - all approvals received
    // Proceed with document processing
} elseif ($result === true) {
    // Approval recorded - more approvals needed
} else {
    // Error occurred - $result contains error message
}
```

#### 4. Record Rejection

```php
$result = $actionService->reject($workflow, [
    'comments' => 'Mandatory rejection reason'
]);

if ($result === 'rejected') {
    // Workflow rejected - update document accordingly
}
```

#### 5. Check Workflow Status

```php
// Get workflow instance
$workflow = WorkflowInstance::where('documentable_type', YourModel::class)
    ->where('documentable_id', $yourDocument->id)
    ->latest()
    ->first();

// Check completion
if ($workflow->isComplete()) {
    echo "Workflow completed at {$workflow->completed_at}";
}

// Get progress
$percentage = $workflow->getCompletionPercentage();
echo "Progress: {$percentage}%";

// Get status
$status = $workflow->getProgressStatus();
echo "Status: {$status}"; // "Completed", "Overdue", "Pending", etc.
```

#### 6. Get Workflow History

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

#### 7. Check for Overdue Workflows

```php
// Get all overdue workflows (for scheduled task)
$overdueWorkflows = WorkflowInstance::overdue()
    ->where('status', 'pending')
    ->get();

foreach ($overdueWorkflows as $workflow) {
    // Handle according to your business rules
    $actionService->handleOverdueWorkflow($workflow);
}
```

---

### Event Hooks

The Workflow plugin can be extended through OctoberCMS events:

#### Workflow Started Event

```php
// In your plugin
Event::listen('workflow.instance.created', function($workflow) {
    // Send notification
    // Log to external system
    // Update dashboard metrics
});
```

#### Approval Recorded Event

```php
Event::listen('workflow.action.approved', function($action, $workflow) {
    // Update approval metrics
    // Notify next approvers
    // Update document status
});
```

#### Workflow Completed Event

```php
Event::listen('workflow.instance.completed', function($workflow) {
    // Trigger document processing
    // Send completion notifications
    // Update reporting dashboards
});
```

**Note**: These events are placeholders for future implementation. Not currently dispatched in v1.0.0.

---

## Usage Examples

### Example 1: Basic Purchase Order Workflow

```php
use Omsb\Workflow\Services\ApprovalPathService;
use Omsb\Workflow\Services\WorkflowActionService;
use Omsb\Workflow\Services\WorkflowActionService;

// 1. Create Purchase Order
$po = PurchaseOrder::create([
    'po_number' => 'PO-HQ-2024-00123',
    'vendor_id' => 10,
    'site_id' => 1,
    'total_amount' => 35000.00,
    'status' => 'draft',
    'created_by' => BackendAuth::getUser()->id
]);

// 2. Start approval workflow
$workflowService = new WorkflowService();

try {
    $workflow = $workflowService->startPurchaseOrderWorkflow($po);
    Flash::success("Purchase Order sent for approval. Workflow: {$workflow->workflow_code}");
} catch (\Exception $e) {
    Flash::error("Failed to start workflow: " . $e->getMessage());
    return;
}

// 3. Approver takes action
$actionService = new WorkflowActionService();

$result = $actionService->approve($workflow, [
    'comments' => 'Approved - pricing is competitive'
]);

if ($result === 'completed') {
    Flash::success('All approvals received! Purchase Order is now approved.');
    
    // Proceed with PO processing
    $po->update(['status' => 'approved']);
    
    // Notify purchasing department
    // Send PO to vendor
    // etc.
}
```

### Example 2: Multi-Approver Quorum Workflow

```php
// Organization has rule: "3 out of 5 department heads must approve"

// Setup approval rule (in Organization plugin)
use Omsb\Workflow\Services\WorkflowActionService;

$rule = Approval::create([
    'code' => 'PO_HQ_10K_TO_50K',
    'document_type' => 'purchase_order',
    'site_id' => 1,
    'floor_limit' => 10000.01,
    'ceiling_limit' => 50000,
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5,
    'eligible_staff_ids' => [5, 8, 12, 15, 18], // 5 dept heads
    'approval_timeout_days' => 5,
    'is_active' => true
]);

// Start workflow for $35K PO
$workflow = $workflowService->startPurchaseOrderWorkflow($po);

// First approval (Dept Head 1)
BackendAuth::login(User::find(5));
$result = $actionService->approve($workflow, ['comments' => 'Approved']);
// Result: true (need 2 more)

// Second approval (Dept Head 2)
BackendAuth::login(User::find(8));
$result = $actionService->approve($workflow, ['comments' => 'Approved']);
// Result: true (need 1 more)

// Third approval (Dept Head 3)
BackendAuth::login(User::find(12));
$result = $actionService->approve($workflow, ['comments' => 'Approved']);
// Result: 'completed' (3 of 5 threshold met)

echo "Quorum achieved! Workflow completed.";
```

### Example 3: Preview Approval Path

```php
// Show user what approvals will be required before submitting

$po = new PurchaseOrder([
    'site_id' => 1,
    'total_amount' => 75000.00,
    'budget_type' => 'Capital'
]);

$preview = $workflowService->previewWorkflow($po, 'purchase_order', [
    'document_attributes' => [
        'budget_type' => 'Capital',
        'urgency' => 'high'
    ]
]);

// Display preview in UI
foreach ($preview as $step) {
    echo "<div class='approval-step'>";
    echo "  <strong>Step {$step['step']}</strong>: {$step['description']}<br>";
    echo "  <small>Estimated: {$step['estimated_days']} days</small>";
    echo "</div>";
}

// Output:
// Step 1: Requires approval from John Smith (Purchasing Manager)
//   Estimated: 3 days
// Step 2: Requires 2 out of 3 approvals (Department Heads)
//   Estimated: 5 days
// Step 3: Requires approval from Jane Doe (CEO)
//   Estimated: 7 days
```

### Example 4: Handle Workflow Rejection

```php
// Approver rejects the workflow
$result = $actionService->reject($workflow, [
    'comments' => 'Budget allocation insufficient for this quarter. Please revise amount.'
]);

if ($result === 'rejected') {
    // Update document
    $po->update([
        'status' => 'rejected',
        'rejection_reason' => 'Budget allocation insufficient'
    ]);
    
    // Notify creator
    $creator = Staff::find($po->created_by);
    Mail::send('procurement::emails.po_rejected', [
        'po' => $po,
        'workflow' => $workflow,
        'rejection_reason' => 'Budget allocation insufficient'
    ], function($message) use ($creator) {
        $message->to($creator->email);
    });
    
    Flash::warning('Purchase Order rejected and creator notified.');
}
```

### Example 5: Overdue Workflow Handling (Scheduled Task)

```php
// In Plugin.php register method
public function registerSchedule($schedule)
{
    // Run daily at 9 AM
    $schedule->call(function () {
        $this->handleOverdueWorkflows();
    })->dailyAt('09:00');
}

protected function handleOverdueWorkflows()
{
    use Omsb\Workflow\Models\WorkflowInstance;
    use Omsb\Workflow\Services\WorkflowActionService;
    
    $actionService = new WorkflowActionService();
    
    // Get overdue workflows
    $overdueWorkflows = WorkflowInstance::where('status', 'pending')
        ->where('due_at', '<', now())
        ->where('is_overdue', false)
        ->get();
    
    foreach ($overdueWorkflows as $workflow) {
        // Mark as overdue
        $workflow->update(['is_overdue' => true]);
        
        // Handle according to rules
        $actionService->handleOverdueWorkflow($workflow);
        
        // Log for monitoring
        \Log::warning("Workflow {$workflow->workflow_code} is overdue", [
            'document_type' => $workflow->document_type,
            'amount' => $workflow->document_amount,
            'due_at' => $workflow->due_at
        ]);
    }
}
```

### Example 6: Custom Document Type Integration

```php
// Integrating Workflow with a custom document type

namespace Omsb\CustomPlugin\Models;

class CustomDocument extends Model
{
    // 1. Add status field
    protected $fillable = ['status', 'total_amount', ...];
    
    // 2. Add relationship to workflow
    public $morphMany = [
        'workflows' => [
            \Omsb\Workflow\Models\WorkflowInstance::class,
            'name' => 'documentable'
        ]
    ];
    
    // 3. Add method to start workflow
    public function submitForApproval()
    {
        $workflowService = new \Omsb\Workflow\Services\WorkflowService();
        
        return $workflowService->startWorkflow($this, 'custom_document', [
            'notes' => 'Custom document approval',
            'document_attributes' => [
                'custom_field' => $this->custom_field,
                'priority' => $this->priority
            ]
        ]);
    }
    
    // 4. Add method to check approval status
    public function isApproved()
    {
        $workflow = $this->workflows()
            ->where('status', 'completed')
            ->latest()
            ->first();
        
        return !is_null($workflow);
    }
    
    // 5. Add method to get current workflow
    public function getCurrentWorkflow()
    {
        return $this->workflows()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();
    }
}

// Setup approval rules in Organization plugin
Approval::create([
    'code' => 'CUSTOM_DOC_APPROVAL',
    'document_type' => 'custom_document',
    'floor_limit' => 0,
    'ceiling_limit' => 100000,
    'approval_type' => 'single',
    'staff_id' => $manager->id,
    'is_active' => true
]);

// Use in controller
public function onSubmit()
{
    $document = CustomDocument::find(post('id'));
    
    try {
        $workflow = $document->submitForApproval();
        Flash::success("Document submitted for approval. Code: {$workflow->workflow_code}");
    } catch (\Exception $e) {
        Flash::error("Submission failed: " . $e->getMessage());
    }
}
```

---

## Components

### Current Status

The Workflow plugin **does not currently provide any frontend components** for use in themes or pages.

**From Plugin.php**:
```php
public function registerComponents()
{
    return []; // No components registered
}
```

### Potential Future Components

Future versions could provide these components for frontend integration:

#### 1. WorkflowStatus Component

Display workflow progress for a document:

```php
// Potential component (not yet implemented)
'Omsb\Workflow\Components\WorkflowStatus' => 'workflowStatus'

// Usage in page:
[workflowStatus]
documentType = "purchase_order"
documentId = {{ :id }}
==

<div class="workflow-status">
    {{ component.progress }}% complete
</div>
```

#### 2. ApprovalAction Component

Allow approvers to approve/reject documents:

```php
// Potential component (not yet implemented)
'Omsb\Workflow\Components\ApprovalAction' => 'approvalAction'

// Usage in page:
[approvalAction]
workflowCode = {{ workflowCode }}
==

{{ component.renderApprovalButtons() }}
```

#### 3. WorkflowHistory Component

Display approval history:

```php
// Potential component (not yet implemented)
'Omsb\Workflow\Components\WorkflowHistory' => 'workflowHistory'
```

**Note**: These components are suggestions for future enhancement. Currently, workflow functionality is backend-only.

---

## Future Improvements

### Planned Features

#### 1. **Parallel Approval Paths**

Support for multiple concurrent approval streams:

```php
// Example: Both Finance AND Legal must approve simultaneously
$workflow->approval_path = [
    'parallel' => [
        'finance_path' => [12, 15, 18],    // Finance approval chain
        'legal_path' => [22, 25]           // Legal approval chain
    ],
    'final' => [30]                        // CEO final approval
];
```

**Benefits**:
- Faster approval for documents requiring multiple department sign-offs
- Reduces bottlenecks in cross-functional approvals
- Maintains accountability through separated paths

#### 2. **Conditional Approval Logic**

Dynamic routing based on document attributes:

```php
// Example: Route to different approvers based on vendor
$approvalRule->conditions = [
    'if' => ['vendor_type' => 'preferred'],
    'then' => ['approver' => 'procurement_manager'],
    'else' => ['approver' => 'ceo']
];

// Example: Additional approval for foreign purchases
$approvalRule->conditions = [
    'if' => ['currency' => '!= MYR'],
    'then' => ['add_approver' => 'treasury_manager']
];
```

**Use Cases**:
- Vendor-specific approval routing
- Foreign currency transaction handling
- Risk-based approval escalation

#### 3. **Approval Templates**

Pre-configured workflows for common scenarios:

```php
// Create template
WorkflowTemplate::create([
    'code' => 'STANDARD_CAPEX',
    'name' => 'Standard Capital Expenditure Approval',
    'document_types' => ['purchase_order', 'purchase_request'],
    'approval_path_template' => [
        ['role' => 'requester_manager', 'amount_max' => 10000],
        ['role' => 'dept_head', 'amount_max' => 50000],
        ['role' => 'finance_manager', 'amount_max' => 100000],
        ['role' => 'ceo', 'amount_min' => 100000]
    ]
]);

// Apply template
$workflow = $workflowService->startFromTemplate($document, 'STANDARD_CAPEX');
```

**Benefits**:
- Faster setup for new document types
- Standardization across organization
- Easy template versioning and updates

#### 4. **Integration APIs (RESTful)**

External system integration endpoints:

```php
// REST API endpoints
POST   /api/workflow/start              // Start workflow from external system
GET    /api/workflow/{code}             // Get workflow status
POST   /api/workflow/{code}/approve     // Record approval
POST   /api/workflow/{code}/reject      // Record rejection
GET    /api/workflow/{code}/history     // Get approval history
GET    /api/workflow/pending            // List pending approvals for user

// Example request
POST /api/workflow/start
{
    "document_type": "purchase_order",
    "document_id": 123,
    "amount": 35000.00,
    "site_id": 1,
    "metadata": {
        "budget_type": "Operating",
        "urgency": "normal"
    }
}

// Response
{
    "workflow_code": "WF-PUR-202401-00123",
    "status": "pending",
    "approval_path": [12, 15, 18],
    "current_step": 1,
    "due_at": "2024-01-20T10:30:00Z"
}
```

**Use Cases**:
- Mobile app integration
- Third-party ERP system integration
- Workflow automation tools

#### 5. **Mobile Push Notifications**

Real-time notifications for approvers:

```php
// In WorkflowService::notifyApprovers()
protected function notifyApprovers($workflow, $approvalRule)
{
    $approvers = $this->getEligibleApprovers($approvalRule);
    
    foreach ($approvers as $approver) {
        // Send push notification
        PushNotification::send($approver->device_token, [
            'title' => 'Approval Required',
            'body' => "{$workflow->document_type} requires your approval",
            'data' => [
                'workflow_code' => $workflow->workflow_code,
                'amount' => $workflow->document_amount,
                'due_at' => $workflow->due_at
            ]
        ]);
        
        // Send email backup
        Mail::send('workflow::emails.approval_required', 
            ['workflow' => $workflow], 
            function($message) use ($approver) {
                $message->to($approver->email);
            }
        );
    }
}
```

**Channels**:
- Push notifications (iOS/Android)
- Email notifications
- SMS for urgent approvals
- Slack/Teams integration

#### 6. **Advanced Reporting Dashboard**

Comprehensive workflow analytics:

```php
// Report widgets
- Approval turnaround time by document type
- Bottleneck identification (which steps take longest)
- Approver workload distribution
- Rejection rate analysis
- Timeout and escalation trends
- Approval efficiency by department/site

// Example dashboard
class WorkflowPerformanceWidget extends ReportWidgetBase
{
    public function render()
    {
        return [
            'avg_approval_time' => $this->getAverageApprovalTime(),
            'bottleneck_steps' => $this->getBottleneckSteps(),
            'top_approvers' => $this->getTopApprovers(),
            'timeout_rate' => $this->getTimeoutRate()
        ];
    }
}
```

#### 7. **Delegation Management**

Enhanced delegation capabilities:

```php
// Temporary delegation
Delegation::create([
    'delegator_id' => $manager->id,
    'delegate_id' => $assistant->id,
    'effective_from' => '2024-01-15',
    'effective_to' => '2024-01-30',
    'delegation_reason' => 'On annual leave',
    'approval_types' => ['purchase_order', 'purchase_request'],
    'max_amount' => 50000
]);

// Auto-delegation based on absence
if ($approver->isOnLeave()) {
    $delegate = $approver->getAutomaticDelegate();
    $actionService->delegateApproval($workflow, $delegate);
}
```

### Performance Optimizations

#### 1. **Workflow Caching**

Cache active workflows for faster lookup:

```php
// Cache workflow instances
Cache::remember("workflow:{$workflowCode}", 3600, function() use ($workflowCode) {
    return WorkflowInstance::where('workflow_code', $workflowCode)->first();
});

// Cache approval paths
Cache::remember("approval_path:{$documentType}:{$amount}:{$siteId}", 3600, function() {
    return $this->determineApprovalPath(...);
});
```

#### 2. **Background Processing**

Move heavy operations to queues:

```php
// Queue workflow notifications
Queue::push(new SendApprovalNotifications($workflow, $approvers));

// Queue overdue workflow checks
Queue::push(new CheckOverdueWorkflows());

// Queue workflow analytics updates
Queue::push(new UpdateWorkflowMetrics($workflow));
```

#### 3. **Archive Strategy**

Archive completed workflows for performance:

```php
// Monthly archival job
public function archiveCompletedWorkflows()
{
    $cutoffDate = now()->subMonths(6);
    
    $completedWorkflows = WorkflowInstance::completed()
        ->where('completed_at', '<', $cutoffDate)
        ->get();
    
    foreach ($completedWorkflows as $workflow) {
        // Move to archive table
        WorkflowArchive::create($workflow->toArray());
        
        // Soft delete from main table
        $workflow->delete();
    }
}
```

### Integration Enhancements

#### 1. **Webhook Support**

Trigger webhooks on workflow events:

```php
// Webhook configuration
WorkflowWebhook::create([
    'event' => 'workflow.completed',
    'url' => 'https://external-system.com/webhook/approval',
    'secret' => 'webhook_secret_key',
    'document_types' => ['purchase_order', 'purchase_request']
]);

// Dispatch webhook
Event::listen('workflow.completed', function($workflow) {
    $webhooks = WorkflowWebhook::where('event', 'workflow.completed')->get();
    
    foreach ($webhooks as $webhook) {
        Http::post($webhook->url, [
            'event' => 'workflow.completed',
            'workflow_code' => $workflow->workflow_code,
            'document_type' => $workflow->document_type,
            'completed_at' => $workflow->completed_at,
            'signature' => hash_hmac('sha256', $workflow->workflow_code, $webhook->secret)
        ]);
    }
});
```

#### 2. **Better Feeder Integration**

Comprehensive activity tracking:

```php
// After each workflow action
Feed::create([
    'user_id' => $approver->id,
    'action_type' => 'workflow_approval',
    'feedable_type' => WorkflowInstance::class,
    'feedable_id' => $workflow->id,
    'additional_data' => [
        'workflow_code' => $workflow->workflow_code,
        'document_type' => $workflow->document_type,
        'document_number' => $workflow->documentable->document_number,
        'approval_step' => $workflow->current_step,
        'action' => 'approved',
        'comments' => $action->comments
    ]
]);

// Display in activity feed
"John Smith approved Purchase Order PO-HQ-2024-00123 (Step 2 of 3)"
```

### User Experience Improvements

#### 1. **Approval Workspace**

Centralized approval dashboard:

```php
// My Approvals widget
class MyApprovalsWidget extends DashboardWidgetBase
{
    public function render()
    {
        $user = BackendAuth::getUser();
        
        return [
            'pending' => $this->getPendingApprovals($user),
            'overdue' => $this->getOverdueApprovals($user),
            'completed_today' => $this->getCompletedToday($user),
            'avg_response_time' => $this->getAvgResponseTime($user)
        ];
    }
}
```

#### 2. **Batch Approval**

Approve multiple workflows at once:

```php
public function onBatchApprove()
{
    $workflowCodes = post('workflow_codes');
    $comments = post('comments');
    
    $actionService = new WorkflowActionService();
    $results = [];
    
    foreach ($workflowCodes as $code) {
        $workflow = WorkflowInstance::where('workflow_code', $code)->first();
        $results[$code] = $actionService->approve($workflow, ['comments' => $comments]);
    }
    
    return ['results' => $results];
}
```

#### 3. **Approval Reminders**

Automated reminder system:

```php
// Daily reminder for pending approvals
public function sendApprovalReminders()
{
    $pendingWorkflows = WorkflowInstance::pending()
        ->where('due_at', '>', now())
        ->where('due_at', '<=', now()->addDays(2))
        ->get();
    
    foreach ($pendingWorkflows as $workflow) {
        $approvers = $this->getEligibleApprovers($workflow);
        
        foreach ($approvers as $approver) {
            Mail::send('workflow::emails.approval_reminder', [
                'workflow' => $workflow,
                'approver' => $approver,
                'days_remaining' => $workflow->due_at->diffInDays(now())
            ], function($message) use ($approver) {
                $message->to($approver->email)
                    ->subject('Reminder: Approval Required');
            });
        }
    }
}
```

---

## Conclusion

The Workflow plugin is a critical component of the OMSB system, providing robust workflow execution and tracking capabilities. By maintaining clear separation of concerns with the Organization plugin (which defines approval rules), it enables flexible, scalable approval processes across all document types.

### Key Strengths
- ✅ Automatic approval path determination
- ✅ Support for complex multi-approver scenarios
- ✅ Comprehensive audit trail
- ✅ Flexible integration with any document type
- ✅ Clean architecture with service layer separation

### Current Limitations
- ⚠️ No frontend components (backend-only)
- ⚠️ Limited notification system (placeholder implementation)
- ⚠️ No REST API for external integration
- ⚠️ No batch approval capabilities

### Next Steps
See [Future Improvements](#future-improvements) section for planned enhancements and optimization opportunities.

---

**Document Version**: 1.0.0  
**Last Updated**: January 2024  
**Plugin Version**: 1.0.0  
**Maintained By**: OMSB Development Team
