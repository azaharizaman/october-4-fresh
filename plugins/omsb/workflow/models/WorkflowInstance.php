<?php namespace Omsb\Workflow\Models;

use Model;
use BackendAuth;

/**
 * WorkflowInstance Model
 * 
 * Tracks individual document workflow transitions (audit trail)
 *
 * @property int $id
 * @property string $workflowable_type Polymorphic document type
 * @property int $workflowable_id Polymorphic document ID
 * @property string $action Action performed (submit, approve, reject, cancel, etc.)
 * @property string|null $comments User comments
 * @property \Carbon\Carbon $transitioned_at Transition timestamp
 * @property int $workflow_definition_id Parent workflow
 * @property int|null $from_status_id Previous status
 * @property int $to_status_id New status
 * @property int|null $transition_id Transition used
 * @property int $performed_by User who performed the action
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowInstance extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_instances';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'workflowable_type',
        'workflowable_id',
        'action',
        'comments',
        'transitioned_at',
        'workflow_definition_id',
        'from_status_id',
        'to_status_id',
        'transition_id',
        'performed_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'comments',
        'from_status_id',
        'transition_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'workflowable_type' => 'required|max:255',
        'workflowable_id' => 'required|integer',
        'action' => 'required|max:255',
        'transitioned_at' => 'required|date',
        'workflow_definition_id' => 'required|integer|exists:omsb_workflow_definitions,id',
        'from_status_id' => 'nullable|integer|exists:omsb_workflow_statuses,id',
        'to_status_id' => 'required|integer|exists:omsb_workflow_statuses,id',
        'transition_id' => 'nullable|integer|exists:omsb_workflow_transitions,id',
        'performed_by' => 'required|integer|exists:backend_users,id'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'transitioned_at'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'workflow_definition' => [
            WorkflowDefinition::class
        ],
        'from_status' => [
            WorkflowStatus::class,
            'key' => 'from_status_id'
        ],
        'to_status' => [
            WorkflowStatus::class,
            'key' => 'to_status_id'
        ],
        'transition' => [
            WorkflowTransition::class
        ],
        'performer' => [
            \Backend\Models\User::class,
            'key' => 'performed_by'
        ]
    ];

    public $morphTo = [
        'workflowable' => []
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-set performed_by and transitioned_at
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->performed_by = BackendAuth::getUser()->id;
            }
            
            if (!$model->transitioned_at) {
                $model->transitioned_at = now();
            }
        });
    }

    /**
     * Get formatted description of the transition
     */
    public function getDescriptionAttribute(): string
    {
        $desc = $this->performer->full_name ?? 'System';
        $desc .= ' ' . $this->action;
        
        if ($this->from_status) {
            $desc .= ' from ' . $this->from_status->name;
        }
        
        $desc .= ' to ' . $this->to_status->name;
        
        return $desc;
    }

    /**
     * Scope: Filter by document
     */
    public function scopeForDocument($query, string $type, int $id)
    {
        return $query->where('workflowable_type', $type)
                     ->where('workflowable_id', $id);
    }

    /**
     * Scope: Filter by workflow
     */
    public function scopeForWorkflow($query, int $workflowId)
    {
        return $query->where('workflow_definition_id', $workflowId);
    }

    /**
     * Scope: Filter by performer
     */
    public function scopeByPerformer($query, int $userId)
    {
        return $query->where('performed_by', $userId);
    }

    /**
     * Scope: Recent transitions first
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('transitioned_at', 'desc');
    }

    /**
     * Get history for a specific document
     */
    public static function getHistoryForDocument(string $type, int $id)
    {
        return self::forDocument($type, $id)
            ->with(['from_status', 'to_status', 'transition', 'performer'])
            ->recent()
            ->get();
    }
}
