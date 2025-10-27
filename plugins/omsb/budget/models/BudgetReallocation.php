<?php namespace Omsb\Budget\Models;

use Model;
use BackendAuth;

/**
 * BudgetReallocation Model
 * 
 * Manages budget reallocations within the same site
 * This is a CONTROLLED DOCUMENT - requires document number from Registrar plugin
 *
 * @property int $id
 * @property string $document_number Unique document number from registrar
 * @property \Carbon\Carbon $reallocation_date Reallocation date
 * @property float $amount Reallocation amount
 * @property string $status Document status
 * @property int $budget_id Affected budget
 * @property int $from_gl_account_id Source GL account
 * @property int $to_gl_account_id Destination GL account
 * @property string $reason Reallocation reason/justification
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
class BudgetReallocation extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Omsb\Registrar\Traits\HasFinancialDocumentProtection;

    /**
     * @var string Document type code for registrar
     */
    public $documentTypeCode = 'BUDGET_REALLOCATION';

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
    public $table = 'omsb_budget_reallocations';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'document_number',
        'reallocation_date',
        'amount',
        'status',
        'budget_id',
        'from_gl_account_id',
        'to_gl_account_id',
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
        'document_number' => 'required|unique:omsb_budget_reallocations,document_number',
        'reallocation_date' => 'required|date',
        'amount' => 'required|numeric|min:0.01',
        'status' => 'required|in:draft,submitted,approved,rejected,cancelled,completed',
        'budget_id' => 'required|integer|exists:omsb_budget_budgets,id',
        'from_gl_account_id' => 'required|integer|exists:omsb_organization_gl_accounts,id|different:to_gl_account_id',
        'to_gl_account_id' => 'required|integer|exists:omsb_organization_gl_accounts,id|different:from_gl_account_id',
        'reason' => 'required|max:500'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'document_number.required' => 'Document number is required',
        'document_number.unique' => 'This document number is already in use',
        'reallocation_date.required' => 'Reallocation date is required',
        'amount.required' => 'Reallocation amount is required',
        'amount.min' => 'Reallocation amount must be greater than 0',
        'budget_id.required' => 'Budget is required',
        'from_gl_account_id.required' => 'Source GL account is required',
        'from_gl_account_id.different' => 'Source and destination GL accounts must be different',
        'to_gl_account_id.required' => 'Destination GL account is required',
        'to_gl_account_id.different' => 'Source and destination GL accounts must be different',
        'reason.required' => 'Reallocation reason is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'reallocation_date',
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
        'budget' => [
            Budget::class
        ],
        'from_gl_account' => [
            'Omsb\Organization\Models\GlAccount',
            'key' => 'from_gl_account_id'
        ],
        'to_gl_account' => [
            'Omsb\Organization\Models\GlAccount',
            'key' => 'to_gl_account_id'
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
            
            // Set default reallocation date
            if (!$model->reallocation_date) {
                $model->reallocation_date = now();
            }
        });

        // Validate same site requirement
        static::saving(function ($model) {
            $model->validateSameSiteReallocation();
        });
    }

    /**
     * Validate that GL accounts belong to the same site as the budget
     */
    protected function validateSameSiteReallocation(): void
    {
        if (!$this->budget_id || !$this->from_gl_account_id || !$this->to_gl_account_id) {
            return;
        }

        $budget = Budget::find($this->budget_id);
        $fromGlAccount = \Omsb\Organization\Models\GlAccount::find($this->from_gl_account_id);
        $toGlAccount = \Omsb\Organization\Models\GlAccount::find($this->to_gl_account_id);

        if ($budget && $fromGlAccount && $toGlAccount) {
            // All GL accounts must belong to the same site as the budget
            if ($fromGlAccount->site_id !== $budget->site_id) {
                throw new \ValidationException([
                    'from_gl_account_id' => 'Source GL account must belong to the same site as the budget.'
                ]);
            }
            
            if ($toGlAccount->site_id !== $budget->site_id) {
                throw new \ValidationException([
                    'to_gl_account_id' => 'Destination GL account must belong to the same site as the budget.'
                ]);
            }
        }
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->document_number;
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
     * Scope: Filter by budget
     */
    public function scopeForBudget($query, int $budgetId)
    {
        return $query->where('budget_id', $budgetId);
    }

    /**
     * Scope: Filter by source GL account
     */
    public function scopeFromGlAccount($query, int $glAccountId)
    {
        return $query->where('from_gl_account_id', $glAccountId);
    }

    /**
     * Scope: Filter by destination GL account
     */
    public function scopeToGlAccount($query, int $glAccountId)
    {
        return $query->where('to_gl_account_id', $glAccountId);
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

    /**
     * Get GL account options for dropdown (filtered by budget's site)
     */
    public function getFromGlAccountIdOptions(): array
    {
        if (!$this->budget_id) {
            return [];
        }

        $budget = Budget::find($this->budget_id);
        if (!$budget) {
            return [];
        }

        return \Omsb\Organization\Models\GlAccount::active()
            ->transactable()
            ->forSite($budget->site_id)
            ->orderBy('account_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Get GL account options for dropdown (filtered by budget's site)
     */
    public function getToGlAccountIdOptions(): array
    {
        if (!$this->budget_id) {
            return [];
        }

        $budget = Budget::find($this->budget_id);
        if (!$budget) {
            return [];
        }

        return \Omsb\Organization\Models\GlAccount::active()
            ->transactable()
            ->forSite($budget->site_id)
            ->orderBy('account_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }
}
