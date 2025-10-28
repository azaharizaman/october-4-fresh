<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * WorkflowInstance Model
 * 
 * Manages ongoing approval workflow instances.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowInstance extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_instances';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'workflow_code',
        'status',
        'document_type',
        'document_amount',
        'current_step',
        'total_steps_required',
        'steps_completed',
        'approval_path',
        'started_at',
        'completed_at',
        'due_at',
        'is_overdue',
        'current_approval_type',
        'approvals_required',
        'approvals_received',
        'rejections_received',
        'is_escalated',
        'escalated_at',
        'escalation_reason',
        'workflow_notes',
        'metadata'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'workflow_code' => 'required|unique:omsb_workflow_instances,workflow_code',
        'status' => 'required|in:pending,in_progress,completed,failed,cancelled',
        'document_type' => 'required|string',
        'current_approval_type' => 'in:single,quorum,majority,unanimous',
        'approvals_required' => 'required|integer|min:1',
        'total_steps_required' => 'required|integer|min:1',
        'steps_completed' => 'integer|min:0'
    ];

    /**
     * @var array dates
     */
    protected $dates = [
        'started_at',
        'completed_at',
        'due_at',
        'escalated_at',
        'deleted_at'
    ];

    /**
     * @var array casts
     */
    protected $casts = [
        'is_overdue' => 'boolean',
        'is_escalated' => 'boolean',
        'approval_path' => 'array',
        'metadata' => 'array',
        'document_amount' => 'decimal:2'
    ];

    /**
     * @var array belongsTo relationships
     */
    public $belongsTo = [
        'current_approval_rule' => [\Omsb\Organization\Models\Approval::class],
        'site' => [\Omsb\Organization\Models\Site::class],
        'created_by_user' => [\Backend\Models\User::class, 'key' => 'created_by']
    ];

    /**
     * @var array hasMany relationships
     */
    public $hasMany = [
        'workflow_actions' => [WorkflowAction::class],
        'approvals' => [WorkflowAction::class, 'conditions' => "action = 'approve'"],
        'rejections' => [WorkflowAction::class, 'conditions' => "action = 'reject'"]
    ];

    /**
     * @var array morphTo relationships
     */
    public $morphTo = [
        'workflowable' => []
    ];

    /**
     * Scope for pending workflows
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for in progress workflows
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for completed workflows
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for overdue workflows
     */
    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    /**
     * Scope for escalated workflows
     */
    public function scopeEscalated($query)
    {
        return $query->where('is_escalated', true);
    }

    /**
     * Check if workflow is complete
     */
    public function isComplete()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if workflow has sufficient approvals for current step
     */
    public function hasSufficientApprovals()
    {
        return $this->approvals_received >= $this->approvals_required;
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage()
    {
        if ($this->total_steps_required == 0) return 0;
        return round(($this->steps_completed / $this->total_steps_required) * 100, 2);
    }

    /**
     * Get workflow progress status
     */
    public function getProgressStatus()
    {
        if ($this->isComplete()) {
            return 'Completed';
        }
        
        if ($this->is_overdue) {
            return 'Overdue';
        }
        
        if ($this->is_escalated) {
            return 'Escalated';
        }
        
        return ucfirst($this->status);
    }
}
