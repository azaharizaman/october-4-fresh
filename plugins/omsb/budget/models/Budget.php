<?php namespace Omsb\Budget\Models;

use Model;
use BackendAuth;

/**
 * Budget Model
 * 
 * Manages yearly budgets with support for calculated fields and budget utilization tracking
 * Budget is NOT a controlled document (no running number from registrar)
 *
 * @property int $id
 * @property string $budget_code Budget code (free input, e.g., BGT/SGH/FEMS/MTC/5080190/25)
 * @property string $description Budget description
 * @property int $year Budget year
 * @property \Carbon\Carbon $effective_from Effective start date
 * @property \Carbon\Carbon $effective_to Effective end date
 * @property float $allocated_amount Initial allocated budget amount
 * @property string $status Budget status (draft, approved, active, expired, cancelled)
 * @property int $gl_account_id Associated GL Account
 * @property int $site_id Budget owner site
 * @property string|null $service_code Service department code
 * @property int|null $created_by Backend user who created this
 * @property int|null $approved_by Backend user who approved this
 * @property \Carbon\Carbon|null $approved_at Approval timestamp
 * @property string|null $notes Additional notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Budget extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_budget_budgets';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'budget_code',
        'description',
        'year',
        'effective_from',
        'effective_to',
        'allocated_amount',
        'status',
        'gl_account_id',
        'site_id',
        'service_code',
        'created_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'service_code',
        'created_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'budget_code' => 'required|max:100',
        'description' => 'required|max:500',
        'year' => 'required|integer|min:2000|max:2100',
        'effective_from' => 'required|date',
        'effective_to' => 'required|date|after:effective_from',
        'allocated_amount' => 'required|numeric|min:0',
        'status' => 'required|in:draft,approved,active,expired,cancelled',
        'gl_account_id' => 'required|integer|exists:omsb_organization_gl_accounts,id',
        'site_id' => 'required|integer|exists:omsb_organization_sites,id',
        'service_code' => 'nullable|max:10'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'budget_code.required' => 'Budget code is required',
        'description.required' => 'Budget description is required',
        'year.required' => 'Budget year is required',
        'effective_from.required' => 'Effective from date is required',
        'effective_to.required' => 'Effective to date is required',
        'effective_to.after' => 'Effective to date must be after effective from date',
        'allocated_amount.required' => 'Allocated amount is required',
        'allocated_amount.min' => 'Allocated amount must be greater than or equal to 0',
        'gl_account_id.required' => 'GL Account is required',
        'site_id.required' => 'Site is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'effective_from',
        'effective_to',
        'approved_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'year' => 'integer',
        'allocated_amount' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'gl_account' => [
            'Omsb\Organization\Models\GlAccount'
        ],
        'site' => [
            'Omsb\Organization\Models\Site'
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

    public $hasMany = [
        'transfers_outward' => [
            BudgetTransfer::class,
            'key' => 'from_budget_id'
        ],
        'transfers_inward' => [
            BudgetTransfer::class,
            'key' => 'to_budget_id'
        ],
        'adjustments' => [
            BudgetAdjustment::class,
            'key' => 'budget_id'
        ],
        'reallocations' => [
            BudgetReallocation::class,
            'key' => 'budget_id'
        ]
    ];

    public $morphMany = [
        'feeds' => [
            'Omsb\Feeder\Models\Feed',
            'name' => 'feedable'
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
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->budget_code . ' - ' . $this->description;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled'
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * CALCULATED FIELDS
     */

    /**
     * Get total amount transferred out
     */
    public function getTotalTransferredOutAttribute(): float
    {
        return (float) $this->transfers_outward()
            ->where('status', 'approved')
            ->sum('amount');
    }

    /**
     * Get total amount transferred in
     */
    public function getTotalTransferredInAttribute(): float
    {
        return (float) $this->transfers_inward()
            ->where('status', 'approved')
            ->sum('amount');
    }

    /**
     * Get total adjustments (can be positive or negative)
     */
    public function getTotalAdjustmentsAttribute(): float
    {
        return (float) $this->adjustments()
            ->where('status', 'approved')
            ->sum('adjustment_amount');
    }

    /**
     * Get total reallocations (can be positive or negative)
     */
    public function getTotalReallocationsAttribute(): float
    {
        return (float) $this->reallocations()
            ->where('status', 'approved')
            ->sum('amount');
    }

    /**
     * Get current budget amount after all transactions
     */
    public function getCurrentBudgetAttribute(): float
    {
        return $this->allocated_amount 
            + $this->total_transferred_in
            - $this->total_transferred_out
            + $this->total_adjustments
            + $this->total_reallocations;
    }

    /**
     * Get total utilized amount from Purchase Orders
     * This checks POs that reference this budget and are in certain statuses
     */
    public function getUtilizedAmountAttribute(): float
    {
        // TODO: Implement after integrating with Procurement plugin
        // This should sum PO amounts where:
        // - PO is linked to this budget (via gl_account_id and site_id match)
        // - PO status is approved/completed
        // - PO effective dates overlap with budget effective dates
        // - PO is NOT voided (exclude POs that are voided, as per HasFinancialDocumentProtection trait)
        return 0.0;
    }

    /**
     * Get available balance
     */
    public function getAvailableBalanceAttribute(): float
    {
        return $this->current_budget - $this->utilized_amount;
    }

    /**
     * Get utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->current_budget === 0) {
            return 0.0;
        }
        
        return ($this->utilized_amount / $this->current_budget) * 100;
    }

    /**
     * Check if budget has sufficient balance for given amount
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    /**
     * Check if adding this amount would exceed budget
     */
    public function wouldExceedBudget(float $amount): bool
    {
        return !$this->hasSufficientBalance($amount);
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
     * Scope: Active budgets only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter by year
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope: Filter by site
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope: Filter by GL Account
     */
    public function scopeForGlAccount($query, int $glAccountId)
    {
        return $query->where('gl_account_id', $glAccountId);
    }

    /**
     * Scope: Filter by service
     */
    public function scopeByService($query, $serviceCode)
    {
        return $query->where('service_code', $serviceCode);
    }

    /**
     * Scope: Budgets effective on a specific date
     */
    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where('effective_to', '>=', $date);
    }

    /**
     * Check if budget is editable
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if budget can be deleted
     */
    public function isDeletable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if budget can be approved
     */
    public function canApprove(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * DROPDOWN OPTIONS
     */

    /**
     * Get site options for dropdown
     */
    public function getSiteIdOptions(): array
    {
        return \Omsb\Organization\Models\Site::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get GL Account options for dropdown
     */
    public function getGlAccountIdOptions(): array
    {
        // Only show transactable (non-header) and active GL accounts
        return \Omsb\Organization\Models\GlAccount::active()
            ->transactable()
            ->orderBy('account_code')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Get service code options for dropdown
     */
    public function getServiceCodeOptions(): array
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceDropdownOptions();
    }

    /**
     * Get service details for this budget
     */
    public function getServiceAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceByCode($this->service_code);
    }

    /**
     * Get service name
     */
    public function getServiceNameAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceName($this->service_code);
    }
}
