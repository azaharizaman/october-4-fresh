<?php namespace Omsb\Budget\Models;

use Model;
use BackendAuth;

/**
 * BudgetTransfer Model
 * 
 * Manages budget transfers between different budgets (intersite)
 * This is a CONTROLLED DOCUMENT - requires document number from Registrar plugin
 *
 * @property int $id
 * @property string $document_number Unique document number from registrar
 * @property string $transfer_type Type of transfer (outward, inward)
 * @property \Carbon\Carbon $transfer_date Transfer date
 * @property float $amount Transfer amount
 * @property string $status Document status
 * @property int $from_budget_id Source budget
 * @property int $to_budget_id Destination budget
 * @property string|null $reason Transfer reason/justification
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
class BudgetTransfer extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Omsb\Registrar\Traits\HasFinancialDocumentProtection;

    /**
     * @var string Document type code for registrar
     */
    public $documentTypeCode = 'BUDGET_TRANSFER';

    /**
     * @var array Statuses that prevent editing
     */
    public $protectedStatuses = ['approved', 'completed', 'cancelled', 'voided'];

    /**
     * @var array Statuses that prevent amount changes
     */
    public $amountProtectedStatuses = ['approved', 'completed'];

    /**
     * @var string table name
     */
    public $table = 'omsb_budget_transfers';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'document_number',
        'transfer_type',
        'transfer_date',
        'amount',
        'status',
        'from_budget_id',
        'to_budget_id',
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'registry_id',
        'is_voided',
        'voided_at',
        'voided_by',
        'void_reason',
        'previous_status'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'registry_id',
        'voided_at',
        'voided_by',
        'void_reason',
        'previous_status'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'document_number' => 'required|unique:omsb_budget_transfers,document_number',
        'transfer_type' => 'required|in:outward,inward',
        'transfer_date' => 'required|date',
        'amount' => 'required|numeric|min:0.01',
        'status' => 'required|in:draft,submitted,approved,rejected,cancelled,completed',
        'from_budget_id' => 'required|integer|exists:omsb_budget_budgets,id|different:to_budget_id',
        'to_budget_id' => 'required|integer|exists:omsb_budget_budgets,id|different:from_budget_id'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'document_number.required' => 'Document number is required',
        'document_number.unique' => 'This document number is already in use',
        'transfer_date.required' => 'Transfer date is required',
        'amount.required' => 'Transfer amount is required',
        'amount.min' => 'Transfer amount must be greater than 0',
        'from_budget_id.required' => 'Source budget is required',
        'from_budget_id.different' => 'Source and destination budgets must be different',
        'to_budget_id.required' => 'Destination budget is required',
        'to_budget_id.different' => 'Source and destination budgets must be different'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'transfer_date',
        'approved_at',
        'voided_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'is_voided' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'from_budget' => [
            Budget::class,
            'key' => 'from_budget_id'
        ],
        'to_budget' => [
            Budget::class,
            'key' => 'to_budget_id'
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
            
            // Set default transfer date
            if (!$model->transfer_date) {
                $model->transfer_date = now();
            }
        });

        // Validate intersite transfer requirement
        static::saving(function ($model) {
            $model->validateIntersiteTransfer();
        });
    }

    /**
     * Validate that this is truly an intersite transfer
     */
    protected function validateIntersiteTransfer(): void
    {
        if (!$this->from_budget_id || !$this->to_budget_id) {
            return;
        }

        $fromBudget = Budget::find($this->from_budget_id);
        $toBudget = Budget::find($this->to_budget_id);

        if ($fromBudget && $toBudget) {
            // Budget transfers must be between different sites (intersite)
            if ($fromBudget->site_id === $toBudget->site_id) {
                throw new \ValidationException([
                    'to_budget_id' => 'Budget transfers must be between different sites. Use Budget Reallocation for transfers within the same site.'
                ]);
            }
        }
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->document_number . ' - ' . $this->transfer_type;
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
     * Get transfer type label
     */
    public function getTransferTypeLabelAttribute(): string
    {
        $labels = [
            'outward' => 'Outward',
            'inward' => 'Inward'
        ];
        
        return $labels[$this->transfer_type] ?? $this->transfer_type;
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
     * Scope: Filter by transfer type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('transfer_type', $type);
    }

    /**
     * Scope: Filter by source budget
     */
    public function scopeFromBudget($query, int $budgetId)
    {
        return $query->where('from_budget_id', $budgetId);
    }

    /**
     * Scope: Filter by destination budget
     */
    public function scopeToBudget($query, int $budgetId)
    {
        return $query->where('to_budget_id', $budgetId);
    }

    /**
     * DROPDOWN OPTIONS
     */

    /**
     * Get budget options for dropdown
     */
    public function getFromBudgetIdOptions(): array
    {
        return Budget::active()
            ->orderBy('budget_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Get budget options for dropdown
     */
    public function getToBudgetIdOptions(): array
    {
        return Budget::active()
            ->orderBy('budget_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }
}
