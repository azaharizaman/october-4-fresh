<?php namespace Omsb\Organization\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;
use ValidationException;

/**
 * FinancialPeriod Model
 * 
 * Manages financial/accounting periods for GL posting, budgets, and financial reporting.
 * Controls when financial transactions can be posted and edited across all modules.
 * 
 * Unlike InventoryPeriod (operational focus), FinancialPeriod enforces accounting compliance
 * and prevents backdated financial transactions after period closing.
 *
 * @property int $id
 * @property string $period_code Unique identifier (e.g., "FY2025-Q1", "FY2025-01")
 * @property string $period_name Display name (e.g., "Q1 FY2025", "January FY2025")
 * @property string $period_type Period type (monthly, quarterly, yearly)
 * @property \Carbon\Carbon $start_date Period start
 * @property \Carbon\Carbon $end_date Period end
 * @property int $fiscal_year Fiscal year (e.g., 2025)
 * @property int|null $fiscal_quarter Fiscal quarter (1-4) if applicable
 * @property int|null $fiscal_month Fiscal month (1-12) if applicable
 * @property string $status Status (draft, open, soft_closed, closed, locked)
 * @property bool $is_year_end Is this a year-end period (13th period for adjustments)
 * @property bool $allow_backdated_posting Allow posting transactions with earlier dates
 * @property \Carbon\Carbon|null $soft_closed_at When AP/AR closed (GL still open)
 * @property \Carbon\Carbon|null $closed_at When period was fully closed
 * @property \Carbon\Carbon|null $locked_at When period was permanently locked
 * @property int|null $soft_closed_by User who soft-closed
 * @property int|null $closed_by User who closed
 * @property int|null $locked_by User who locked
 * @property string|null $closing_notes Notes about period closing
 * @property int|null $previous_period_id Link to previous period for opening balances
 * @property int $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class FinancialPeriod extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_financial_periods';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'period_code',
        'period_name',
        'period_type',
        'start_date',
        'end_date',
        'fiscal_year',
        'fiscal_quarter',
        'fiscal_month',
        'status',
        'is_year_end',
        'allow_backdated_posting',
        'soft_closed_at',
        'closed_at',
        'locked_at',
        'soft_closed_by',
        'closed_by',
        'locked_by',
        'closing_notes',
        'previous_period_id'
    ];

    /**
     * @var array nullable attributes
     */
    protected $nullable = [
        'fiscal_quarter',
        'fiscal_month',
        'soft_closed_at',
        'closed_at',
        'locked_at',
        'soft_closed_by',
        'closed_by',
        'locked_by',
        'closing_notes',
        'previous_period_id'
    ];

    /**
     * @var array validation rules
     */
    public $rules = [
        'period_code' => 'required|max:30|unique:omsb_organization_financial_periods,period_code',
        'period_name' => 'required|max:255',
        'period_type' => 'required|in:monthly,quarterly,yearly',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'fiscal_year' => 'required|integer|min:2000|max:2100',
        'fiscal_quarter' => 'nullable|integer|min:1|max:4',
        'fiscal_month' => 'nullable|integer|min:1|max:13',
        'status' => 'required|in:draft,open,soft_closed,closed,locked',
        'is_year_end' => 'boolean',
        'allow_backdated_posting' => 'boolean',
        'previous_period_id' => 'nullable|integer|exists:omsb_organization_financial_periods,id'
    ];

    /**
     * @var array dates
     */
    protected $dates = [
        'start_date',
        'end_date',
        'soft_closed_at',
        'closed_at',
        'locked_at',
        'deleted_at'
    ];

    /**
     * @var array casts
     */
    protected $casts = [
        'is_year_end' => 'boolean',
        'allow_backdated_posting' => 'boolean'
    ];

    /**
     * @var array relations
     */
    public $belongsTo = [
        'previous_period' => [
            self::class,
            'key' => 'previous_period_id'
        ],
        'soft_closer' => [
            \Backend\Models\User::class,
            'key' => 'soft_closed_by'
        ],
        'closer' => [
            \Backend\Models\User::class,
            'key' => 'closed_by'
        ],
        'locker' => [
            \Backend\Models\User::class,
            'key' => 'locked_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'next_periods' => [
            self::class,
            'key' => 'previous_period_id'
        ],
        'budgets' => [
            'Omsb\Budget\Models\Budget',
            'key' => 'financial_period_id'
        ]
    ];

    /**
     * Boot model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }

            // Auto-calculate fiscal quarter/month from dates
            if (!$model->fiscal_quarter && $model->period_type === 'quarterly') {
                $model->fiscal_quarter = $model->start_date->quarter;
            }
            if (!$model->fiscal_month && $model->period_type === 'monthly') {
                $model->fiscal_month = $model->start_date->month;
            }
        });

        static::saving(function ($model) {
            // Validate period overlap
            $overlapping = self::where('id', '!=', $model->id ?? 0)
                ->where(function ($query) use ($model) {
                    $query->whereBetween('start_date', [$model->start_date, $model->end_date])
                          ->orWhereBetween('end_date', [$model->start_date, $model->end_date])
                          ->orWhere(function ($q) use ($model) {
                              $q->where('start_date', '<=', $model->start_date)
                                ->where('end_date', '>=', $model->end_date);
                          });
                })
                ->first();

            if ($overlapping) {
                throw new ValidationException([
                    'start_date' => 'Period dates overlap with: ' . $overlapping->period_name
                ]);
            }
        });

        static::deleting(function ($model) {
            if (in_array($model->status, ['closed', 'locked'])) {
                throw new ValidationException(['status' => 'Cannot delete closed/locked periods']);
            }
        });
    }

    /**
     * Check if period allows posting
     */
    public function allowsPosting(): bool
    {
        return in_array($this->status, ['open', 'soft_closed']);
    }

    /**
     * Check if period allows GL posting
     */
    public function allowsGLPosting(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if period allows AP/AR posting
     */
    public function allowsAPARPosting(): bool
    {
        // AP/AR can post when open, but not after soft-close
        return $this->status === 'open';
    }

    /**
     * Check if backdated transactions allowed
     */
    public function allowsBackdating(): bool
    {
        return $this->allow_backdated_posting && $this->status === 'open';
    }

    /**
     * Soft close (close AP/AR, keep GL open)
     */
    public function softClose(): bool
    {
        if ($this->status !== 'open') {
            throw new ValidationException(['status' => 'Only open periods can be soft-closed']);
        }

        $this->status = 'soft_closed';
        $this->soft_closed_at = Carbon::now();
        $this->soft_closed_by = BackendAuth::getUser()?->id;

        return $this->save();
    }

    /**
     * Close period (no more posting)
     */
    public function close(): bool
    {
        if (!in_array($this->status, ['open', 'soft_closed'])) {
            throw new ValidationException(['status' => 'Period cannot be closed from current status']);
        }

        $this->status = 'closed';
        $this->closed_at = Carbon::now();
        $this->closed_by = BackendAuth::getUser()?->id;

        return $this->save();
    }

    /**
     * Lock period permanently
     */
    public function lock(): bool
    {
        if ($this->status !== 'closed') {
            throw new ValidationException(['status' => 'Only closed periods can be locked']);
        }

        $this->status = 'locked';
        $this->locked_at = Carbon::now();
        $this->locked_by = BackendAuth::getUser()?->id;

        return $this->save();
    }

    /**
     * Reopen period (admin function)
     */
    public function reopen(): bool
    {
        if ($this->status === 'locked') {
            throw new ValidationException(['status' => 'Locked periods cannot be reopened']);
        }

        $this->status = 'open';
        $this->soft_closed_at = null;
        $this->closed_at = null;
        $this->soft_closed_by = null;
        $this->closed_by = null;

        return $this->save();
    }

    /**
     * Get current active period
     */
    public static function getCurrentPeriod()
    {
        return self::where('status', 'open')
            ->where('start_date', '<=', Carbon::today())
            ->where('end_date', '>=', Carbon::today())
            ->first();
    }

    /**
     * Scope: Open periods
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope: Closed periods
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['closed', 'locked']);
    }

    /**
     * Scope: For fiscal year
     */
    public function scopeForFiscalYear($query, int $year)
    {
        return $query->where('fiscal_year', $year);
    }

    /**
     * Scope: Current period
     */
    public function scopeCurrent($query)
    {
        $today = Carbon::today();
        return $query->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->period_code . ' - ' . $this->period_name;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'secondary',
            'open' => 'success',
            'soft_closed' => 'warning',
            'closed' => 'danger',
            'locked' => 'dark',
            default => 'secondary'
        };
    }
}
