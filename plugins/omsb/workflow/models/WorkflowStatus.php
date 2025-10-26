<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * WorkflowStatus Model
 * 
 * Defines statuses within a workflow
 *
 * @property int $id
 * @property string $code Unique status code
 * @property string $name Status name
 * @property string|null $description Status description
 * @property string $color Status color for UI display
 * @property bool $is_initial Initial status flag
 * @property bool $is_final Final/terminal status flag
 * @property bool $is_active Active status
 * @property int $sort_order Sort order
 * @property int $workflow_definition_id Parent workflow
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowStatus extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_statuses';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'is_initial',
        'is_final',
        'is_active',
        'sort_order',
        'workflow_definition_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_workflow_statuses,code',
        'name' => 'required|max:255',
        'color' => 'required|max:7',
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'workflow_definition_id' => 'required|integer|exists:omsb_workflow_definitions,id'
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
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'workflow_definition' => [
            WorkflowDefinition::class
        ]
    ];

    public $hasMany = [
        'transitions_from' => [
            WorkflowTransition::class,
            'key' => 'from_status_id'
        ],
        'transitions_to' => [
            WorkflowTransition::class,
            'key' => 'to_status_id'
        ],
        'instances' => [
            WorkflowInstance::class,
            'key' => 'to_status_id'
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
     * Scope: Active statuses only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Initial statuses
     */
    public function scopeInitial($query)
    {
        return $query->where('is_initial', true);
    }

    /**
     * Scope: Final statuses
     */
    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    /**
     * Check if this is a terminal status
     */
    public function isTerminal(): bool
    {
        return $this->is_final;
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
}
