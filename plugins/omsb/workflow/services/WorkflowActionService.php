<?php namespace Omsb\Workflow\Services;

use Omsb\Workflow\Models\WorkflowInstance;
use Omsb\Workflow\Models\WorkflowAction;
use Omsb\Organization\Models\Approval;
use Backend\Facades\BackendAuth;

/**
 * WorkflowActionService
 * 
 * Handles approval and rejection actions on workflow instances.
 * Manages step progression and completion.
 */
class WorkflowActionService
{
    /**
     * Approve current step of workflow
     * 
     * @param WorkflowInstance $workflow
     * @param array $options
     * @return bool|string Returns true if approved, 'completed' if workflow finished, error message if failed
     */
    public function approve(WorkflowInstance $workflow, $options = [])
    {
        $user = BackendAuth::getUser();
        
        if (!$user) {
            return 'No authenticated user found';
        }
        
        // Check if workflow is still pending
        if ($workflow->status !== 'pending') {
            return 'Workflow is not in pending status';
        }
        
        // Check if user can approve this step
        if (!$this->canUserApprove($workflow, $user)) {
            return 'User not authorized to approve this workflow step';
        }
        
        // Check if user already acted on this step
        if ($this->hasUserActedOnCurrentStep($workflow, $user)) {
            return 'User has already acted on this workflow step';
        }
        
        // Record the approval action
        $action = WorkflowAction::create([
            'workflow_instance_id' => $workflow->id,
            'approval_rule_id' => $workflow->current_approval_rule_id,
            'step_name' => $workflow->current_step,
            'action_type' => 'approve',
            'action_by' => $user->id,
            'action_at' => now(),
            'comments' => $options['comments'] ?? null,
            'metadata' => $options['metadata'] ?? null
        ]);
        
        // Increment approvals received
        $workflow->increment('approvals_received');
        $workflow->refresh();
        
        // Check if current step is satisfied
        if ($this->isCurrentStepSatisfied($workflow)) {
            return $this->advanceToNextStep($workflow);
        }
        
        return true;
    }
    
    /**
     * Reject current step of workflow
     */
    public function reject(WorkflowInstance $workflow, $options = [])
    {
        $user = BackendAuth::getUser();
        
        if (!$user) {
            return 'No authenticated user found';
        }
        
        // Check if user can reject this step
        if (!$this->canUserApprove($workflow, $user)) {
            return 'User not authorized to reject this workflow step';
        }
        
        // Record the rejection action
        $action = WorkflowAction::create([
            'workflow_instance_id' => $workflow->id,
            'approval_rule_id' => $workflow->current_approval_rule_id,
            'step_name' => $workflow->current_step,
            'action_type' => 'reject',
            'action_by' => $user->id,
            'action_at' => now(),
            'comments' => $options['comments'] ?? 'Workflow rejected',
            'metadata' => $options['metadata'] ?? null
        ]);
        
        // Mark workflow as rejected
        $workflow->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'rejection_reason' => $options['comments'] ?? 'Workflow rejected'
        ]);
        
        // Update document status
        $this->updateDocumentStatus($workflow, 'rejected');
        
        return 'rejected';
    }
    
    /**
     * Check if user can approve current workflow step
     */
    protected function canUserApprove(WorkflowInstance $workflow, $user)
    {
        $currentRule = Approval::find($workflow->current_approval_rule_id);
        
        if (!$currentRule) {
            return false;
        }
        
        // Check approval type
        if ($currentRule->approval_type === 'single') {
            return $currentRule->staff_id === $user->id;
        }
        
        if ($currentRule->approval_type === 'quorum') {
            // For quorum, check if user is in eligible approvers list
            $eligibleApprovers = $currentRule->eligible_approvers_list ?? [];
            return in_array($user->id, $eligibleApprovers);
        }
        
        if ($currentRule->approval_type === 'position') {
            // Check if user holds the required position at the site
            return $this->userHoldsPosition($user, $currentRule, $workflow->site_id);
        }
        
        return false;
    }
    
    /**
     * Check if user has already acted on current step
     */
    protected function hasUserActedOnCurrentStep(WorkflowInstance $workflow, $user)
    {
        return WorkflowAction::where('workflow_instance_id', $workflow->id)
            ->where('approval_rule_id', $workflow->current_approval_rule_id)
            ->where('action_by', $user->id)
            ->exists();
    }
    
    /**
     * Check if current step approval requirements are satisfied
     */
    protected function isCurrentStepSatisfied(WorkflowInstance $workflow)
    {
        $currentRule = Approval::find($workflow->current_approval_rule_id);
        
        if (!$currentRule) {
            return false;
        }
        
        // For single approver, one approval is enough
        if ($currentRule->approval_type === 'single') {
            return $workflow->approvals_received >= 1;
        }
        
        // For quorum and position, check required approvers count
        return $workflow->approvals_received >= $currentRule->required_approvers;
    }
    
    /**
     * Advance workflow to next step or complete it
     */
    protected function advanceToNextStep(WorkflowInstance $workflow)
    {
        $approvalPath = is_array($workflow->approval_path) ? $workflow->approval_path : json_decode($workflow->approval_path, true);
        
        if (!is_array($approvalPath)) {
            return 'Invalid approval path format';
        }
        
        $currentStepIndex = array_search($workflow->current_approval_rule_id, $approvalPath);
        
        if ($currentStepIndex === false) {
            return 'Current step not found in approval path';
        }
        
        // Increment completed steps
        $workflow->increment('steps_completed');
        
        // Check if there are more steps
        $nextStepIndex = $currentStepIndex + 1;
        
        if ($nextStepIndex >= count($approvalPath)) {
            // Workflow completed
            return $this->completeWorkflow($workflow);
        }
        
        // Move to next step
        $nextRuleId = $approvalPath[$nextStepIndex];
        $nextRule = Approval::find($nextRuleId);
        
        if (!$nextRule) {
            return 'Next approval rule not found';
        }
        
        $workflow->update([
            'current_approval_rule_id' => $nextRule->id,
            'current_step' => $this->getStepName($nextRule),
            'current_approval_type' => $nextRule->approval_type,
            'approvals_required' => $nextRule->required_approvers,
            'approvals_received' => 0,
            'rejections_received' => 0,
            'due_at' => $this->calculateDueDate($nextRule)
        ]);
        
        // Notify next approvers
        $this->notifyApprovers($workflow, $nextRule);
        
        return true;
    }
    
    /**
     * Complete the workflow
     */
    protected function completeWorkflow(WorkflowInstance $workflow)
    {
        $workflow->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        // Update document status to approved/completed
        $this->updateDocumentStatus($workflow, 'approved');
        
        return 'completed';
    }
    
    /**
     * Get step name for approval rule
     */
    protected function getStepName($approvalRule)
    {
        if ($approvalRule->approval_type === 'single') {
            $staffName = $approvalRule->staff ? $approvalRule->staff->full_name : 'Assigned Staff';
            return "Approval by {$staffName}";
        }
        
        if ($approvalRule->approval_type === 'quorum') {
            return "Quorum Approval ({$approvalRule->required_approvers} of {$approvalRule->eligible_approvers})";
        }
        
        return "Approval Step: {$approvalRule->code}";
    }
    
    /**
     * Calculate due date for approval step
     */
    protected function calculateDueDate($approvalRule)
    {
        $days = $approvalRule->approval_timeout_days ?? 3;
        return now()->addDays($days);
    }
    
    /**
     * Update document status
     */
    protected function updateDocumentStatus(WorkflowInstance $workflow, $newStatus)
    {
        $document = $workflow->documentable;
        
        if ($document && is_object($document) && method_exists($document, 'update')) {
            $document->update(['status' => $newStatus]);
        }
    }
    
    /**
     * Check if user holds required position
     */
    protected function userHoldsPosition($user, $approvalRule, $siteId)
    {
        // This would check if user has the required position at the site
        // Implementation depends on how positions are stored in staff model
        
        if (!$user->position) {
            return false;
        }
        
        if ($user->site_id !== $siteId) {
            return false;
        }
        
        $requiredPosition = $approvalRule->required_position;
        return $user->position === $requiredPosition;
    }
    
    /**
     * Notify approvers
     */
    protected function notifyApprovers($workflow, $approvalRule)
    {
        \Log::info("Workflow {$workflow->workflow_code} step requires approval", [
            'rule_code' => $approvalRule->code,
            'step_name' => $workflow->current_step,
            'due_at' => $workflow->due_at
        ]);
        
        // TODO: Implement actual notification system
    }
    
    /**
     * Get workflow actions history
     */
    public function getWorkflowHistory(WorkflowInstance $workflow)
    {
        return WorkflowAction::where('workflow_instance_id', $workflow->id)
            ->with(['actionBy', 'approvalRule'])
            ->orderBy('action_at', 'asc')
            ->get();
    }
    
    /**
     * Check if workflow is overdue
     */
    public function isWorkflowOverdue(WorkflowInstance $workflow)
    {
        if ($workflow->status !== 'pending') {
            return false;
        }
        
        return $workflow->due_at && now()->gt($workflow->due_at);
    }
    
    /**
     * Handle overdue workflows
     */
    public function handleOverdueWorkflow(WorkflowInstance $workflow, $options = [])
    {
        $currentRule = Approval::find($workflow->current_approval_rule_id);
        
        if (!$currentRule) {
            return false;
        }
        
        // Check escalation rules
        if ($currentRule->escalation_enabled && $currentRule->escalation_staff_id) {
            return $this->escalateWorkflow($workflow, $currentRule);
        }
        
        // Auto-reject if configured
        if ($currentRule->auto_reject_on_timeout) {
            return $this->reject($workflow, [
                'comments' => 'Auto-rejected due to timeout',
                'metadata' => ['auto_action' => true, 'reason' => 'timeout']
            ]);
        }
        
        return false;
    }
    
    /**
     * Escalate workflow to higher authority
     */
    protected function escalateWorkflow(WorkflowInstance $workflow, $currentRule)
    {
        // Update current rule to escalated staff
        $workflow->update([
            'current_approval_rule_id' => $currentRule->escalation_staff_id,
            'escalated_at' => now(),
            'escalation_reason' => 'Timeout escalation'
        ]);
        
        // Log escalation action
        WorkflowAction::create([
            'workflow_instance_id' => $workflow->id,
            'approval_rule_id' => $currentRule->id,
            'step_name' => $workflow->current_step,
            'action_type' => 'escalate',
            'action_by' => null, // System action
            'action_at' => now(),
            'comments' => 'Escalated due to timeout',
            'metadata' => ['escalated_to' => $currentRule->escalation_staff_id]
        ]);
        
        return true;
    }
}