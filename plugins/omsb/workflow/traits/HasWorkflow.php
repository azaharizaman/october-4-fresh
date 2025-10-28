<?php namespace Omsb\Workflow\Traits;

use Omsb\Workflow\Models\WorkflowInstance;
use Omsb\Workflow\Services\WorkflowService;
use Omsb\Workflow\Services\WorkflowActionService;
use ValidationException;
use Backend\Facades\BackendAuth;

/**
 * HasWorkflow Trait
 * 
 * Provides workflow/approval capabilities to any model that requires multi-level approval.
 * Models using this trait automatically get:
 * - Workflow instance management
 * - Convenient approval/rejection methods
 * - Workflow status checking
 * - Progress tracking
 * - Workflow history access
 * 
 * Usage in models:
 * ```php
 * use \Omsb\Workflow\Traits\HasWorkflow;
 * 
 * class PurchaseOrder extends Model
 * {
 *     use HasWorkflow;
 *     
 *     // Required property - define your document type code
 *     protected $workflowDocumentType = 'purchase_order';
 *     
 *     // Optional - statuses that can start workflow
 *     protected $workflowEligibleStatuses = ['draft', 'pending'];
 *     
 *     // Optional - status to set when workflow starts
 *     protected $workflowPendingStatus = 'pending_approval';
 *     
 *     // Optional - status to set when workflow completes
 *     protected $workflowApprovedStatus = 'approved';
 *     
 *     // Optional - status to set when workflow is rejected
 *     protected $workflowRejectedStatus = 'rejected';
 * }
 * ```
 * 
 * Required model properties:
 * - $workflowDocumentType (string) - Document type code for workflow routing
 * 
 * Optional model properties:
 * - $workflowEligibleStatuses (array) - Statuses allowed to start workflow (default: ['draft'])
 * - $workflowPendingStatus (string) - Status when workflow starts (default: 'pending_approval')
 * - $workflowApprovedStatus (string) - Status when workflow completes (default: 'approved')
 * - $workflowRejectedStatus (string) - Status when workflow rejected (default: 'rejected')
 * - $workflowAutoSubmit (bool) - Auto-submit to workflow on save (default: false)
 * - $workflowProtectedFields (array) - Fields that cannot be modified during workflow (default: ['*'] = all fields)
 * - $workflowAllowedFields (array) - Fields that CAN be modified during workflow (default: [])
 * 
 * @link https://docs.octobercms.com/4.x/extend/database/traits.html
 */
trait HasWorkflow
{
    /**
     * @var WorkflowService Workflow service instance
     */
    protected $workflowService;

    /**
     * @var WorkflowActionService Action service instance
     */
    protected $workflowActionService;

    /**
     * Boot the trait - set up event handlers
     */
    public static function bootHasWorkflow()
    {
        // Optionally auto-submit to workflow after creation
        static::created(function ($model) {
            if ($model->getWorkflowAutoSubmit()) {
                $model->submitToWorkflow();
            }
        });

        // Prevent modification of documents with active workflows
        static::updating(function ($model) {
            if ($model->hasActiveWorkflow() && !$model->isWorkflowStatusChange()) {
                // Check if any protected fields are being changed
                if ($model->hasProtectedFieldChanges()) {
                    throw new ValidationException([
                        'workflow' => 'Document cannot be modified while approval is in progress.'
                    ]);
                }
            }
        });

        // Prevent deletion of documents with active workflows
        static::deleting(function ($model) {
            if ($model->hasActiveWorkflow()) {
                throw new ValidationException([
                    'workflow' => 'Document cannot be deleted while approval is in progress. Cancel the workflow first.'
                ]);
            }
        });
    }

    /**
     * Initialize the trait - create service instances
     */
    public function initializeHasWorkflow()
    {
        $this->workflowService = new WorkflowService();
        $this->workflowActionService = new WorkflowActionService();
    }

    /**
     * Relationship to workflow instances (polymorphic)
     */
    public function workflows()
    {
        return $this->morphMany(WorkflowInstance::class, 'workflowable');
    }

    /**
     * Get the current active workflow instance
     * 
     * @return WorkflowInstance|null
     */
    public function getCurrentWorkflow()
    {
        return $this->workflows()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();
    }

    /**
     * Get the most recent completed workflow
     * 
     * @return WorkflowInstance|null
     */
    public function getCompletedWorkflow()
    {
        return $this->workflows()
            ->where('status', 'completed')
            ->latest()
            ->first();
    }

    /**
     * Submit document to workflow for approval
     * 
     * @param array $options Workflow options (notes, metadata, document_attributes)
     * @return WorkflowInstance
     * @throws \Exception
     */
    public function submitToWorkflow($options = [])
    {
        // Validate document type is defined
        if (!$this->getWorkflowDocumentType()) {
            throw new \Exception('Model must define $workflowDocumentType property');
        }

        // Validate current status allows workflow submission
        if (!$this->isEligibleForWorkflow()) {
            throw new \Exception(
                'Document status "' . $this->status . '" is not eligible for workflow submission. ' .
                'Eligible statuses: ' . implode(', ', $this->getWorkflowEligibleStatuses())
            );
        }

        // Check if there's already an active workflow
        if ($this->hasActiveWorkflow()) {
            throw new \Exception('Document already has an active workflow');
        }

        // Ensure services are initialized
        if (!$this->workflowService) {
            $this->initializeHasWorkflow();
        }

        // Start the workflow
        $workflow = $this->workflowService->startWorkflow(
            $this,
            $this->getWorkflowDocumentType(),
            $options
        );

        // Update document status
        $this->updateQuietly([
            'status' => $this->getWorkflowPendingStatus()
        ]);

        return $workflow;
    }

    /**
     * Preview the approval path without starting workflow
     * 
     * @param array $options Preview options
     * @return array
     */
    public function previewWorkflow($options = [])
    {
        if (!$this->workflowService) {
            $this->initializeHasWorkflow();
        }

        return $this->workflowService->previewWorkflow(
            $this,
            $this->getWorkflowDocumentType(),
            $options
        );
    }

    /**
     * Approve the current workflow step
     * 
     * @param array $options Approval options (comments, metadata)
     * @return bool|string True if approved, 'completed' if finished, error message if failed
     */
    public function approveWorkflow($options = [])
    {
        $workflow = $this->getCurrentWorkflow();

        if (!$workflow) {
            throw new \Exception('No active workflow found for this document');
        }

        if (!$this->workflowActionService) {
            $this->initializeHasWorkflow();
        }

        $result = $this->workflowActionService->approve($workflow, $options);

        // If workflow completed, update document status
        if ($result === 'completed') {
            $this->updateQuietly([
                'status' => $this->getWorkflowApprovedStatus()
            ]);
        }

        return $result;
    }

    /**
     * Reject the current workflow step
     * 
     * @param array $options Rejection options (REQUIRED: comments with rejection reason)
     * @return string 'rejected' if successful, error message if failed
     */
    public function rejectWorkflow($options = [])
    {
        $workflow = $this->getCurrentWorkflow();

        if (!$workflow) {
            throw new \Exception('No active workflow found for this document');
        }

        if (empty($options['comments'])) {
            throw new ValidationException([
                'comments' => 'Rejection reason is required'
            ]);
        }

        if (!$this->workflowActionService) {
            $this->initializeHasWorkflow();
        }

        $result = $this->workflowActionService->reject($workflow, $options);

        // If rejection successful, update document status
        if ($result === 'rejected') {
            $this->updateQuietly([
                'status' => $this->getWorkflowRejectedStatus()
            ]);
        }

        return $result;
    }

    /**
     * Cancel the current workflow
     * 
     * @param string $reason Cancellation reason
     * @return bool
     */
    public function cancelWorkflow($reason = null)
    {
        $workflow = $this->getCurrentWorkflow();

        if (!$workflow) {
            return false;
        }

        $workflow->update([
            'status' => 'cancelled',
            'workflow_notes' => $reason ?: 'Workflow cancelled by user'
        ]);

        // Reset document status to draft or initial status
        $this->updateQuietly([
            'status' => $this->getWorkflowEligibleStatuses()[0] ?? 'draft'
        ]);

        return true;
    }

    /**
     * Get complete workflow history for this document
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWorkflowHistory()
    {
        $workflow = $this->getCurrentWorkflow() ?: $this->getCompletedWorkflow();

        if (!$workflow) {
            return collect();
        }

        if (!$this->workflowActionService) {
            $this->initializeHasWorkflow();
        }

        return $this->workflowActionService->getWorkflowHistory($workflow);
    }

    /**
     * Check if document has an active workflow
     * 
     * @return bool
     */
    public function hasActiveWorkflow()
    {
        return $this->workflows()
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
    }

    /**
     * Check if document has ever been approved
     * 
     * @return bool
     */
    public function isApproved()
    {
        return $this->workflows()
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Check if document has been rejected
     * 
     * @return bool
     */
    public function isRejected()
    {
        return $this->workflows()
            ->where('status', 'rejected')
            ->exists();
    }

    /**
     * Check if current workflow is overdue
     * 
     * @return bool
     */
    public function isWorkflowOverdue()
    {
        $workflow = $this->getCurrentWorkflow();
        return $workflow ? $workflow->is_overdue : false;
    }

    /**
     * Check if current workflow is escalated
     * 
     * @return bool
     */
    public function isWorkflowEscalated()
    {
        $workflow = $this->getCurrentWorkflow();
        return $workflow ? $workflow->is_escalated : false;
    }

    /**
     * Get current workflow step name
     * 
     * @return string|null
     */
    public function getCurrentStep()
    {
        $workflow = $this->getCurrentWorkflow();
        return $workflow ? $workflow->current_step : null;
    }

    /**
     * Get workflow completion percentage
     * 
     * @return float|null Percentage (0-100) or null if no workflow
     */
    public function getWorkflowProgress()
    {
        $workflow = $this->getCurrentWorkflow();
        return $workflow ? $workflow->getCompletionPercentage() : null;
    }

    /**
     * Get human-readable workflow status
     * 
     * @return string
     */
    public function getWorkflowStatus()
    {
        $workflow = $this->getCurrentWorkflow();
        
        if (!$workflow) {
            return $this->isApproved() ? 'Approved' : 'No Active Workflow';
        }

        return $workflow->getProgressStatus();
    }

    /**
     * Check if document is currently awaiting approval from user
     * 
     * @param \Backend\Models\User|null $user User to check (defaults to current user)
     * @return bool
     */
    public function isAwaitingApprovalFrom($user = null)
    {
        $workflow = $this->getCurrentWorkflow();
        
        if (!$workflow) {
            return false;
        }

        $user = $user ?: BackendAuth::getUser();
        
        if (!$user) {
            return false;
        }

        // Check if user can approve current step
        // This requires checking Organization.Approval rules
        // Implementation depends on approval rule structure
        
        return false; // Placeholder - implement based on your approval logic
    }

    /**
     * Check if current status allows workflow submission
     * 
     * @return bool
     */
    protected function isEligibleForWorkflow()
    {
        $eligibleStatuses = $this->getWorkflowEligibleStatuses();
        return in_array($this->status, $eligibleStatuses);
    }

    /**
     * Check if current update is just a workflow status change
     * (allows status updates during workflow progression)
     * 
     * @return bool
     */
    protected function isWorkflowStatusChange()
    {
        if (!$this->isDirty()) {
            return false;
        }

        $changes = $this->getDirty();
        
        // Only status changed
        if (count($changes) === 1 && isset($changes['status'])) {
            return true;
        }

        // Only status and updated_at changed (automatic)
        if (count($changes) === 2 && isset($changes['status']) && isset($changes['updated_at'])) {
            return true;
        }

        return false;
    }

    /**
     * Get workflow document type code
     * 
     * @return string|null
     */
    protected function getWorkflowDocumentType()
    {
        return property_exists($this, 'workflowDocumentType') 
            ? $this->workflowDocumentType 
            : null;
    }

    /**
     * Get statuses eligible for workflow submission
     * 
     * @return array
     */
    protected function getWorkflowEligibleStatuses()
    {
        return property_exists($this, 'workflowEligibleStatuses') 
            ? $this->workflowEligibleStatuses 
            : ['draft'];
    }

    /**
     * Get status to set when workflow starts
     * 
     * @return string
     */
    protected function getWorkflowPendingStatus()
    {
        return property_exists($this, 'workflowPendingStatus') 
            ? $this->workflowPendingStatus 
            : 'pending_approval';
    }

    /**
     * Get status to set when workflow completes
     * 
     * @return string
     */
    protected function getWorkflowApprovedStatus()
    {
        return property_exists($this, 'workflowApprovedStatus') 
            ? $this->workflowApprovedStatus 
            : 'approved';
    }

    /**
     * Get status to set when workflow is rejected
     * 
     * @return string
     */
    protected function getWorkflowRejectedStatus()
    {
        return property_exists($this, 'workflowRejectedStatus') 
            ? $this->workflowRejectedStatus 
            : 'rejected';
    }

    /**
     * Get whether to auto-submit to workflow on creation
     * 
     * @return bool
     */
    protected function getWorkflowAutoSubmit()
    {
        return property_exists($this, 'workflowAutoSubmit') 
            ? $this->workflowAutoSubmit 
            : false;
    }

    /**
     * Get fields protected from modification during workflow
     * 
     * @return array
     */
    protected function getWorkflowProtectedFields()
    {
        return property_exists($this, 'workflowProtectedFields') 
            ? $this->workflowProtectedFields 
            : ['*']; // Default: protect all fields
    }

    /**
     * Get fields allowed to be modified during workflow
     * 
     * @return array
     */
    protected function getWorkflowAllowedFields()
    {
        return property_exists($this, 'workflowAllowedFields') 
            ? $this->workflowAllowedFields 
            : []; // Default: no fields explicitly allowed
    }

    /**
     * Check if any protected fields are being changed
     * 
     * @return bool
     */
    protected function hasProtectedFieldChanges()
    {
        $dirty = array_keys($this->getDirty());
        
        // Remove updated_at from check (always allowed)
        $dirty = array_diff($dirty, ['updated_at']);
        
        if (empty($dirty)) {
            return false;
        }
        
        $protectedFields = $this->getWorkflowProtectedFields();
        
        // If protecting all fields (wildcard)
        if (in_array('*', $protectedFields)) {
            $allowedFields = $this->getWorkflowAllowedFields();
            $changedProtectedFields = array_diff($dirty, $allowedFields);
            return count($changedProtectedFields) > 0;
        }
        
        // If protecting specific fields
        $changedProtectedFields = array_intersect($dirty, $protectedFields);
        return count($changedProtectedFields) > 0;
    }
}
