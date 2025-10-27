# Workflow Plugin

## Overview

The Workflow plugin manages **workflow execution and tracking** for approval processes across the OMSB system. It works in conjunction with the Organization plugin to orchestrate complex multi-level approval workflows.

## Architecture Philosophy

### Clear Separation of Concerns

**Organization Plugin (Approval Definitions):**
- Defines WHO can approve WHAT and HOW MUCH
- Multi-approver rules (e.g., "3 out of 5 approvers")
- Hierarchy validation rules
- Assignment strategies (position-based, manual)
- Approval limits and delegation policies

**Workflow Plugin (Execution Tracking):**
- Tracks ONGOING approval processes
- Individual approval/rejection actions
- Workflow progress and completion
- Overdue and escalation management
- Audit trail of all actions

## Key Models

### WorkflowInstance
Manages ongoing approval workflow instances for documents requiring approval.

**Core Functionality:**
- **Document Linking**: Uses morphTo to link to any document type (Purchase Request, Stock Adjustment, etc.)
- **Progress Tracking**: Monitors steps completed vs. total steps required
- **Approval Counting**: Tracks approvals received vs. required for current step
- **Timing Management**: Handles start, completion, and due dates with overdue detection
- **Escalation Support**: Manages escalated workflows with reasoning

**Key Fields:**
```php
'workflow_code'         // Unique identifier
'status'               // pending, in_progress, completed, failed, cancelled
'document_type'        // Type of document being approved
'documentable'         // morphTo relationship to actual document
'current_step'         // Current approval step name
'approvals_required'   // How many approvals needed for current step
'approvals_received'   // How many approvals received so far
'approval_path'        // JSON array of approval rule IDs in sequence
```

### WorkflowAction
Tracks individual approval/rejection actions within workflow instances.

**Core Functionality:**
- **Action Recording**: Logs approve, reject, delegate, escalate, comment actions
- **Step Sequencing**: Maintains order of actions within workflow
- **Delegation Tracking**: Records delegation chains and reasons
- **Timing Audit**: Captures when actions were taken and due dates

**Key Fields:**
```php
'action'               // approve, reject, delegate, escalate, comment
'step_sequence'        // Order in the workflow
'comments'             // Approver comments
'rejection_reason'     // Specific reason for rejection
'is_delegated_action'  // Whether this was a delegated action
'action_taken_at'      // When action was performed
```

## Business Logic Flow

### 1. Workflow Initiation
```php
// When a document needs approval, create workflow instance
$approvalRule = Approval::getApplicableRule($document);

$workflow = WorkflowInstance::create([
    'workflow_code' => $this->generateWorkflowCode(),
    'document_type' => 'purchase_request',
    'documentable' => $purchaseRequest,
    'current_approval_rule_id' => $approvalRule->id,
    'approvals_required' => $approvalRule->required_approvers,
    'status' => 'pending'
]);
```

### 2. Approval Processing
```php
// When an approver takes action
$action = WorkflowAction::create([
    'workflow_instance_id' => $workflow->id,
    'action' => 'approve',
    'staff_id' => $approvingStaff->id,
    'approval_rule_id' => $approvalRule->id,
    'step_sequence' => $workflow->getCurrentStepSequence(),
    'action_taken_at' => now()
]);

// Update workflow progress
$workflow->approvals_received++;
if ($workflow->hasSufficientApprovals()) {
    $workflow->moveToNextStep();
}
```

### 3. Multi-Approver Support (Quorum-based)
```php
// For "3 out of 5 approvers" scenarios
$approvalRule = Approval::where([
    'document_type' => 'purchase_request',
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5
])->first();

// Workflow tracks progress toward quorum
if ($workflow->approvals_received >= $approvalRule->required_approvers) {
    $workflow->completeCurrentStep();
}
```

## Integration Points

### With Organization Plugin
- **Approval Rules**: References `omsb_organization_approvals` for approval definitions
- **Staff Hierarchy**: Validates approvers are above creators in hierarchy
- **Site Context**: Respects site-based approval authority

### With Document Plugins (Procurement, Inventory, etc.)
- **MorphTo Relationship**: Can attach to any document type
- **Status Synchronization**: Updates document status based on workflow progress
- **Event Integration**: Triggers document events on workflow completion

### With Feeder Plugin
- **Activity Tracking**: All workflow actions are logged for audit trail
- **Feed Integration**: Workflow progress appears in activity feeds

## Status Transitions

### Workflow Instance Statuses
- **pending**: Workflow created, waiting for first approval
- **in_progress**: At least one approval received, more needed
- **completed**: All required approvals received
- **failed**: Workflow failed due to rejections or timeout
- **cancelled**: Workflow manually cancelled

### Action Types
- **approve**: Staff approves the document
- **reject**: Staff rejects with reason
- **delegate**: Staff delegates approval to another
- **escalate**: Workflow escalated due to timeout
- **comment**: Staff adds comment without decision

## Advanced Features

### Quorum-based Approval
Supports complex scenarios where "X out of Y approvers" must approve:
```php
// Rule: 3 out of 5 department heads must approve
$rule = [
    'approval_type' => 'quorum',
    'required_approvers' => 3,
    'eligible_approvers' => 5,
    'assignment_strategy' => 'position_based',
    'eligible_position_ids' => [1, 2, 3, 4, 5] // 5 dept head positions
];
```

### Timeout and Escalation
Automatic handling of overdue approvals:
```php
// Rule with timeout
$rule = [
    'approval_timeout_days' => 5,
    'timeout_action' => 'escalate',
    'escalation_approval_rule_id' => $seniorRule->id
];

// Workflow checks for overdue and escalates
if ($workflow->due_at < now()) {
    $workflow->escalate($rule->escalation_approval_rule_id);
}
```

### Delegation Chain
Track delegation relationships:
```php
// Original approver delegates to subordinate
WorkflowAction::create([
    'action' => 'delegate',
    'staff_id' => $delegatedToStaff->id,
    'original_staff_id' => $originalApprover->id,
    'is_delegated_action' => true,
    'delegation_reason' => 'On leave until next week'
]);
```

## Reporting and Analytics

### Workflow Performance
- Average approval time by document type
- Bottleneck identification (steps with longest delays)
- Approval success rate by staff/department

### Compliance Tracking
- Overdue workflows requiring attention
- Delegation frequency and patterns
- Approval hierarchy compliance validation

## Configuration

### Environment Settings
```php
// config/workflow.php
return [
    'default_timeout_days' => 7,
    'escalation_enabled' => true,
    'delegation_max_depth' => 3,
    'auto_complete_on_quorum' => true
];
```

### Document Type Registration
```php
// Register document types that support workflows
'supported_documents' => [
    'purchase_request' => \Omsb\Procurement\Models\PurchaseRequest::class,
    'stock_adjustment' => \Omsb\Inventory\Models\StockAdjustment::class,
    // ... other document types
];
```

## Migration History

### Major Architecture Change (October 2025)
**Background**: Originally, the Workflow plugin contained an MLAS (Multi-Level Approval System) table that duplicated approval definition functionality already present in the Organization plugin.

**Changes Made**:
1. **Merged MLAS into Organization Plugin**: All approval definitions moved to `omsb_organization_approvals`
2. **Enhanced Organization Approvals**: Added multi-approver support, quorum-based approval, position-based assignment
3. **Refocused Workflow Plugin**: Now handles only workflow execution and tracking
4. **Created New Models**: `WorkflowInstance` and `WorkflowAction` for execution tracking
5. **Backward Compatibility**: `MLA` model now aliases to `Organization\Approval`

**Benefits**:
- Eliminated duplication between plugins
- Clear separation of concerns (definition vs. execution)
- Enhanced multi-approver capabilities
- Better integration with organizational hierarchy
- Comprehensive audit trail of approval actions

**Business Logic Impact**:
- Organization plugin defines approval rules and policies
- Workflow plugin executes and tracks approval processes
- Complex scenarios like "3 out of 5 approvers" now fully supported
- Enhanced delegation and escalation capabilities
- Better timeout and overdue management

This architectural change provides a solid foundation for complex enterprise approval workflows while maintaining clean plugin boundaries and responsibilities.

## Future Enhancements

### Planned Features
- **Parallel Approval Paths**: Support for multiple concurrent approval streams
- **Conditional Logic**: Dynamic approval routing based on document attributes
- **Approval Templates**: Pre-configured approval flows for common scenarios
- **Integration APIs**: RESTful APIs for external system integration
- **Mobile Notifications**: Push notifications for pending approvals

### Performance Optimizations
- **Workflow Caching**: Cache active workflows for faster lookup
- **Background Processing**: Move heavy workflow processing to queues
- **Archive Strategy**: Archive completed workflows for performance