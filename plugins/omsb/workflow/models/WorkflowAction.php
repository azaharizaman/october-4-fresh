<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * WorkflowAction Model
 * 
 * Represents individual approval/rejection actions within workflow instances.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowAction extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_actions';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'action',
        'step_name',
        'step_sequence',
        'comments',
        'rejection_reason',
        'is_automatic',
        'is_delegated_action',
        'delegation_reason',
        'action_taken_at',
        'due_at',
        'is_overdue_action'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'action' => 'required|in:approve,reject,delegate,escalate,comment',
        'step_sequence' => 'required|integer|min:1',
        'workflow_instance_id' => 'required|exists:omsb_workflow_instances,id',
        'approval_rule_id' => 'required|exists:omsb_organization_approvals,id',
        'staff_id' => 'required|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array dates
     */
    protected $dates = [
        'action_taken_at',
        'due_at',
        'deleted_at'
    ];

    /**
     * @var array casts
     */
    protected $casts = [
        'is_automatic' => 'boolean',
        'is_delegated_action' => 'boolean',
        'is_overdue_action' => 'boolean'
    ];

    /**
     * @var array belongsTo relationships
     */
    public $belongsTo = [
        'workflow_instance' => [WorkflowInstance::class],
        'approval_rule' => [\Omsb\Organization\Models\Approval::class],
        'staff' => [\Omsb\Organization\Models\Staff::class],
        'original_staff' => [\Omsb\Organization\Models\Staff::class, 'key' => 'original_staff_id'],
        'user' => [\Backend\Models\User::class]
    ];

    /**
     * Scope for approved actions
     */
    public function scopeApproved($query)
    {
        return $query->where('action', 'approve');
    }

    /**
     * Scope for rejected actions
     */
    public function scopeRejected($query)
    {
        return $query->where('action', 'reject');
    }

    /**
     * Scope for delegated actions
     */
    public function scopeDelegated($query)
    {
        return $query->where('is_delegated_action', true);
    }

    /**
     * Scope for overdue actions
     */
    public function scopeOverdue($query)
    {
        return $query->where('is_overdue_action', true);
    }
}
