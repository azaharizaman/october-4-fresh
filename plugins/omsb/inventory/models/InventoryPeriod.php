<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * InventoryPeriod Model
 * 
 * Manages inventory accounting periods (monthly, quarterly, yearly).
 * Handles month-end closing and locking of ledger entries.
 *
 * @property int $id
 * @property string $period_code Unique period identifier (e.g., "2024-01")
 * @property string $period_name Display name (e.g., "January 2024")
 * @property string $period_type Type (monthly, quarterly, yearly)
 * @property \Carbon\Carbon $start_date Period start
 * @property \Carbon\Carbon $end_date Period end
 * @property string $status Status (open, closing, closed, locked)
 * @property int $fiscal_year Fiscal year
 * @property string $valuation_method Costing method (FIFO, LIFO, Average)
 * @property \Carbon\Carbon|null $closed_at When period was closed
 * @property \Carbon\Carbon|null $locked_at When period was locked
 * @property bool $is_adjustment_period For year-end adjustments
 * @property string|null $notes Additional information
 * @property int|null $previous_period_id Link to previous period
 * @property int|null $closed_by Staff who closed the period
 * @property int|null $locked_by Staff who locked the period
 * @property int $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class InventoryPeriod extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_inventory_periods';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'period_code',
        'period_name',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'fiscal_year',
        'valuation_method',
        'closed_at',
        'locked_at',
        'is_adjustment_period',
        'notes',
        'previous_period_id',
        'closed_by',
        'locked_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'closed_at',
        'locked_at',
        'notes',
        'previous_period_id',
        'closed_by',
        'locked_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'period_code' => 'required|max:20|unique:omsb_inventory_inventory_periods,period_code',
        'period_name' => 'required|max:255',
        'period_type' => 'required|in:monthly,quarterly,yearly',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required|in:open,closing,closed,locked',
        'fiscal_year' => 'required|integer|min:2000|max:2100',
        'valuation_method' => 'required|in:FIFO,LIFO,Average',
        'is_adjustment_period' => 'boolean',
        'previous_period_id' => 'nullable|integer|exists:omsb_inventory_inventory_periods,id',
        'closed_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'locked_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'period_code.required' => 'Period code is required',
        'period_code.unique' => 'This period code is already in use',
        'period_name.required' => 'Period name is required',
        'period_type.required' => 'Period type is required',
        'start_date.required' => 'Start date is required',
        'end_date.required' => 'End date is required',
        'end_date.after' => 'End date must be after start date',
        'status.required' => 'Status is required',
        'fiscal_year.required' => 'Fiscal year is required',
        'valuation_method.required' => 'Valuation method is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'start_date',
        'end_date',
        'closed_at',
        'locked_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'is_adjustment_period' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'previous_period' => [
            InventoryPeriod::class,
            'key' => 'previous_period_id'
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
            InventoryPeriod::class,
            'key' => 'previous_period_id'
        ],
        'ledger_entries' => [
            InventoryLedger::class,
            'key' => 'inventory_period_id'
        ],
        'valuations' => [
            InventoryValuation::class,
            'key' => 'inventory_period_id'
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
        });

        // Validate period overlap
        static::saving(function ($model) {
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
                throw new \ValidationException([
                    'start_date' => 'Period dates overlap with existing period: ' . $overlapping->period_name
                ]);
            }
        });

        // Prevent deletion of closed/locked periods
        static::deleting(function ($model) {
            if (in_array($model->status, ['closed', 'locked'])) {
                throw new \Exception('Cannot delete closed or locked periods');
            }
        });
    }

    /**
     * Get display name for the period
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->period_code . ' - ' . $this->period_name;
    }

    /**
     * Scope: Open periods only
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope: Closed periods only
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope: Locked periods only
     */
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    /**
     * Scope: Filter by fiscal year
     */
    public function scopeForFiscalYear($query, int $year)
    {
        return $query->where('fiscal_year', $year);
    }

    /**
     * Scope: Filter by period type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('period_type', $type);
    }

    /**
     * Scope: Current period (today falls within date range)
     */
    public function scopeCurrent($query)
    {
        $today = Carbon::today();
        return $query->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    /**
     * Check if period is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if period is closed
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'locked']);
    }

    /**
     * Check if period is locked
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /**
     * Check if period can be closed
     */
    public function canClose(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if period can be locked
     */
    public function canLock(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Close the period
     * Locks all ledger entries and triggers valuation report generation
     * 
     * @return bool
     */
    public function close(): bool
    {
        if (!$this->canClose()) {
            throw new \Exception('Period cannot be closed from current status: ' . $this->status);
        }

        // Wrap in transaction to ensure atomicity
        return \DB::transaction(function () {
            // Lock all ledger entries in this period
            InventoryLedger::inPeriod($this->id)
                ->unlocked()
                ->update(['is_locked' => true]);

            // Update period status
            $this->status = 'closed';
            $this->closed_at = Carbon::now();
            
            // Set closed_by to backend user ID (not staff ID)
            // This matches the created_by pattern used throughout the system
            if (BackendAuth::check()) {
                $this->closed_by = BackendAuth::getUser()->id;
            }

            return $this->save();
        });
    }

    /**
     * Lock the period permanently
     * No modifications allowed after locking
     * 
     * @return bool
     */
    public function lock(): bool
    {
        if (!$this->canLock()) {
            throw new \Exception('Period cannot be locked from current status: ' . $this->status);
        }

        $this->status = 'locked';
        $this->locked_at = Carbon::now();
        
        // Set locked_by to backend user ID (not staff ID)
        // This matches the created_by pattern used throughout the system
        if (BackendAuth::check()) {
            $this->locked_by = BackendAuth::getUser()->id;
        }

        return $this->save();
    }

    /**
     * Reopen a closed period (admin function)
     * Only works if period is closed (not locked)
     * 
     * @return bool
     */
    public function reopen(): bool
    {
        if ($this->status !== 'closed') {
            throw new \Exception('Only closed periods can be reopened');
        }

        // Unlock ledger entries
        InventoryLedger::inPeriod($this->id)
            ->locked()
            ->update(['is_locked' => false]);

        $this->status = 'open';
        $this->closed_at = null;
        $this->closed_by = null;

        return $this->save();
    }

    /**
     * Get previous period options for dropdown
     */
    public function getPreviousPeriodIdOptions(): array
    {
        return self::where('id', '!=', $this->id ?? 0)
            ->where('end_date', '<', $this->start_date ?? Carbon::now())
            ->orderBy('start_date', 'desc')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get total ledger entries count for this period
     */
    public function getTotalEntriesCountAttribute(): int
    {
        return $this->ledger_entries()->count();
    }

    /**
     * Get number of days in period
     */
    public function getDaysInPeriodAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Check if date falls within this period
     */
    public function containsDate(Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }
}
