<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * WorkflowTransition Model
 * 
 * Defines transitions between workflow statuses
 *
 * @property int $id
 * @property string $code Unique transition code
 * @property string $name Transition name
 * @property string|null $description Transition description
 * @property bool $requires_approval Whether transition requires approval
 * @property bool $requires_comment Whether comment is required
 * @property bool $can_reject Whether transition can be rejected
 * @property string|null $rejection_status_code Status code to revert to on rejection
 * @property float|null $min_amount Minimum transaction amount
 * @property float|null $max_amount Maximum transaction amount
 * @property bool $is_active Active status
 * @property int $sort_order Sort order
 * @property int $workflow_definition_id Parent workflow
 * @property int $from_status_id Source status
 * @property int $to_status_id Target status
 * @property int|null $approver_role_id Required approver role
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowTransition extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_transitions';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_approval',
        'requires_comment',
        'can_reject',
        'rejection_status_code',
        'min_amount',
        'max_amount',
        'is_active',
        'sort_order',
        'workflow_definition_id',
        'from_status_id',
        'to_status_id',
        'approver_role_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description',
        'rejection_status_code',
        'min_amount',
        'max_amount',
        'approver_role_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_workflow_transitions,code',
        'name' => 'required|max:255',
        'requires_approval' => 'boolean',
        'requires_comment' => 'boolean',
        'can_reject' => 'boolean',
        'min_amount' => 'nullable|numeric|min:0',
        'max_amount' => 'nullable|numeric|min:0',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'workflow_definition_id' => 'required|integer|exists:omsb_workflow_definitions,id',
        'from_status_id' => 'required|integer|exists:omsb_workflow_statuses,id',
        'to_status_id' => 'required|integer|exists:omsb_workflow_statuses,id',
        'approver_role_id' => 'nullable|integer|exists:omsb_workflow_approver_roles,id'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'requires_approval' => 'boolean',
        'requires_comment' => 'boolean',
        'can_reject' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2'
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
        'approver_role' => [
            ApproverRole::class
        ]
    ];

    public $hasMany = [
        'instances' => [
            WorkflowInstance::class,
            'key' => 'transition_id'
        ]
    ];

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Get full transition description
     */
    public function getFullDescriptionAttribute(): string
    {
        $desc = $this->from_status->name . ' → ' . $this->to_status->name;
        
        if ($this->approver_role) {
            $desc .= ' (by ' . $this->approver_role->name . ')';
        }
        
        return $desc;
    }

    /**
     * Scope: Active transitions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by from status
     */
    public function scopeFromStatus($query, int $statusId)
    {
        return $query->where('from_status_id', $statusId);
    }

    /**
     * Scope: Filter by to status
     */
    public function scopeToStatus($query, int $statusId)
    {
        return $query->where('to_status_id', $statusId);
    }

    /**
     * Check if transition is applicable for given amount
     */
    public function isApplicableForAmount(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }
        
        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }
        
        return true;
    }

    /**
     * Get workflow definition options for dropdown
     */
    public function getWorkflowDefinitionIdOptions(): array
    {
        return WorkflowDefinition::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get from status options for dropdown
     */
    public function getFromStatusIdOptions(): array
    {
        if (!$this->workflow_definition_id) {
            return [];
        }
        
        return WorkflowStatus::where('workflow_definition_id', $this->workflow_definition_id)
            ->active()
            ->orderBy('sort_order')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get to status options for dropdown
     */
    public function getToStatusIdOptions(): array
    {
        if (!$this->workflow_definition_id) {
            return [];
        }
        
        return WorkflowStatus::where('workflow_definition_id', $this->workflow_definition_id)
            ->active()
            ->orderBy('sort_order')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get approver role options for dropdown
     */
    public function getApproverRoleIdOptions(): array
    {
        if (!$this->workflow_definition_id) {
            return [];
        }
        
        return ApproverRole::where('workflow_definition_id', $this->workflow_definition_id)
            ->active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
