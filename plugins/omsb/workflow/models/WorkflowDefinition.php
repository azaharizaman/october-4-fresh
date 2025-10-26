<?php namespace Omsb\Workflow\Models;

use Model;

/**
 * WorkflowDefinition Model
 * 
 * Defines workflows for document types
 *
 * @property int $id
 * @property string $code Unique workflow code
 * @property string $name Workflow name
 * @property string|null $description Workflow description
 * @property string $document_type Fully qualified class name of document
 * @property bool $is_active Active status
 * @property int $max_approval_days Maximum days for approval
 * @property bool $requires_approval Whether workflow requires approval
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WorkflowDefinition extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_workflow_definitions';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'document_type',
        'is_active',
        'max_approval_days',
        'requires_approval'
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
        'code' => 'required|max:255|unique:omsb_workflow_definitions,code',
        'name' => 'required|max:255',
        'document_type' => 'required|max:255',
        'is_active' => 'boolean',
        'max_approval_days' => 'required|integer|min:1',
        'requires_approval' => 'boolean'
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
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'max_approval_days' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'statuses' => [
            WorkflowStatus::class,
            'key' => 'workflow_definition_id',
            'order' => 'sort_order'
        ],
        'transitions' => [
            WorkflowTransition::class,
            'key' => 'workflow_definition_id',
            'order' => 'sort_order'
        ],
        'approver_roles' => [
            ApproverRole::class,
            'key' => 'workflow_definition_id'
        ],
        'instances' => [
            WorkflowInstance::class,
            'key' => 'workflow_definition_id'
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
     * Get initial status
     */
    public function getInitialStatus(): ?WorkflowStatus
    {
        return $this->statuses()->where('is_initial', true)->first();
    }

    /**
     * Get final statuses
     */
    public function getFinalStatuses()
    {
        return $this->statuses()->where('is_final', true)->get();
    }

    /**
     * Scope: Active workflows only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by document type
     */
    public function scopeForDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Get available transitions from a status
     */
    public function getTransitionsFromStatus(int $statusId)
    {
        return $this->transitions()
            ->where('from_status_id', $statusId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if transition is allowed
     */
    public function canTransition(int $fromStatusId, int $toStatusId, float $amount = null): bool
    {
        $transition = $this->transitions()
            ->where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->where('is_active', true)
            ->first();
        
        if (!$transition) {
            return false;
        }
        
        // Check amount constraints if specified
        if ($amount !== null) {
            if ($transition->min_amount && $amount < $transition->min_amount) {
                return false;
            }
            if ($transition->max_amount && $amount > $transition->max_amount) {
                return false;
            }
        }
        
        return true;
    }
}
