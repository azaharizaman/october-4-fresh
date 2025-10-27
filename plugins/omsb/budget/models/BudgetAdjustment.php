<?php namespace Omsb\Budget\Models;

use Model;
use BackendAuth;

/**
 * BudgetAdjustment Model
 * 
 * Manages budget adjustments - modifications to budget amounts
 * This is a CONTROLLED DOCUMENT - requires document number from Registrar plugin
 *
 * @property int $id
 * @property string $document_number Unique document number from registrar
 * @property \Carbon\Carbon $adjustment_date Adjustment date
 * @property float $adjustment_amount Adjustment amount (can be positive or negative)
 * @property string $adjustment_type Type of adjustment (increase, decrease)
 * @property string $status Document status
 * @property int $budget_id Affected budget
 * @property string $reason Adjustment reason/justification
 * @property string|null $notes Additional notes
 * @property int|null $created_by Backend user who created this
 * @property int|null $approved_by Backend user who approved this
 * @property \Carbon\Carbon|null $approved_at Approval timestamp
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class BudgetAdjustment extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_budget_adjustments';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'document_number',
        'adjustment_date',
        'adjustment_amount',
        'adjustment_type',
        'status',
        'budget_id',
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'notes',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'document_number' => 'required|unique:omsb_budget_adjustments,document_number',
        'adjustment_date' => 'required|date',
        'adjustment_amount' => 'required|numeric',
        'adjustment_type' => 'required|in:increase,decrease',
        'status' => 'required|in:draft,submitted,approved,rejected,cancelled,completed',
        'budget_id' => 'required|integer|exists:omsb_budget_budgets,id',
        'reason' => 'required|max:500'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'document_number.required' => 'Document number is required',
        'document_number.unique' => 'This document number is already in use',
        'adjustment_date.required' => 'Adjustment date is required',
        'adjustment_amount.required' => 'Adjustment amount is required',
        'adjustment_type.required' => 'Adjustment type is required',
        'budget_id.required' => 'Budget is required',
        'reason.required' => 'Adjustment reason is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'adjustment_date',
        'approved_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'adjustment_amount' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'budget' => [
            Budget::class
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ],
        'approver' => [
            \Backend\Models\User::class,
            'key' => 'approved_by'
        ]
    ];

    public $morphMany = [
        'feeds' => [
            'Omsb\Feeder\Models\Feed',
            'name' => 'feedable'
        ],
        'workflow_instances' => [
            'Omsb\Workflow\Models\WorkflowInstance',
            'name' => 'workflowable'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-set created_by on creation
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
            
            // Set default status
            if (!$model->status) {
                $model->status = 'draft';
            }
            
            // Set default adjustment date
            if (!$model->adjustment_date) {
                $model->adjustment_date = now();
            }
        });

        // Normalize adjustment amount based on type
        static::saving(function ($model) {
            $model->normalizeAdjustmentAmount();
        });
    }

    /**
     * Normalize adjustment amount based on type
     * Increases should be positive, decreases should be negative
     */
    protected function normalizeAdjustmentAmount(): void
    {
        if ($this->adjustment_type === 'increase' && $this->adjustment_amount < 0) {
            $this->adjustment_amount = abs($this->adjustment_amount);
        } elseif ($this->adjustment_type === 'decrease' && $this->adjustment_amount > 0) {
            $this->adjustment_amount = -abs($this->adjustment_amount);
        }
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->document_number . ' - ' . $this->adjustment_type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed'
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get adjustment type label
     */
    public function getAdjustmentTypeLabelAttribute(): string
    {
        $labels = [
            'increase' => 'Increase',
            'decrease' => 'Decrease'
        ];
        
        return $labels[$this->adjustment_type] ?? $this->adjustment_type;
    }

    /**
     * Get absolute amount (always positive for display)
     */
    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->adjustment_amount);
    }

    /**
     * Check if document is editable
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if document can be deleted
     */
    public function isDeletable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if document can be approved
     */
    public function canApprove(): bool
    {
        return in_array($this->status, ['draft', 'submitted']);
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by adjustment type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('adjustment_type', $type);
    }

    /**
     * Scope: Filter by budget
     */
    public function scopeForBudget($query, int $budgetId)
    {
        return $query->where('budget_id', $budgetId);
    }

    /**
     * Scope: Increases only
     */
    public function scopeIncreases($query)
    {
        return $query->where('adjustment_type', 'increase');
    }

    /**
     * Scope: Decreases only
     */
    public function scopeDecreases($query)
    {
        return $query->where('adjustment_type', 'decrease');
    }

    /**
     * DROPDOWN OPTIONS
     */

    /**
     * Get budget options for dropdown
     */
    public function getBudgetIdOptions(): array
    {
        return Budget::active()
            ->orderBy('budget_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }
}
