<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * ApproverRole Model
 * 
 * Defines approver roles within a workflow
 *
 * @property int $id
 * @property string $code Unique role code
 * @property string $name Role name
 * @property string|null $description Role description
 * @property bool $is_active Active status
 * @property int $workflow_definition_id Parent workflow
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class ApproverRole extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_approver_roles';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
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
        'code' => 'required|max:255|unique:omsb_workflow_approver_roles,code',
        'name' => 'required|max:255',
        'is_active' => 'boolean',
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
        'is_active' => 'boolean'
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
        'transitions' => [
            WorkflowTransition::class,
            'key' => 'approver_role_id'
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
     * Scope: Active roles only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
