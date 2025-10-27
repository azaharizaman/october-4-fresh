<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Omsb\Registrar\Traits\HasControlledDocumentNumber;
use Carbon\Carbon;
use ValidationException;

/**
 * StockAdjustment Model
 * 
 * Records inventory quantity corrections/adjustments.
 * Used to correct discrepancies from physical counts, damage, theft, expiry, etc.
 * Creates InventoryLedger entries for audit trail.
 *
 * @property int $id
 * @property string $adjustment_number Unique document number
 * @property int $warehouse_id Warehouse where adjustment occurs
 * @property \Carbon\Carbon $adjustment_date Date of adjustment
 * @property string $reason_code Reason for adjustment (damage, theft, count_variance, expired, etc.)
 * @property string|null $reference_document Source of adjustment (e.g., physical count number)
 * @property float $total_value_impact Financial impact of adjustment
 * @property string|null $notes Additional notes
 * @property string $status Document status (draft, submitted, approved, completed)
 * @property int|null $approved_by Staff who approved the adjustment
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class StockAdjustment extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use HasControlledDocumentNumber;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_stock_adjustments';

    /**
     * @var string document type code for registrar
     */
    protected string $documentTypeCode = 'SADJ';

    /**
     * @var array statuses that lock the document
     */
    protected array $protectedStatuses = ['approved', 'completed'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'adjustment_number',
        'document_number',
        'registry_id',
        'warehouse_id',
        'adjustment_date',
        'reason_code',
        'reference_document',
        'total_value_impact',
        'notes',
        'status',
        'approved_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'reference_document',
        'notes',
        'approved_by',
        'created_by',
        'registry_id',
        'previous_status'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'adjustment_number' => 'required|max:255|unique:omsb_inventory_stock_adjustments,adjustment_number',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'adjustment_date' => 'required|date',
        'reason_code' => 'required|in:damage,theft,count_variance,expired,obsolete,quality_issue,write_off,correction,other',
        'reference_document' => 'nullable|max:255',
        'total_value_impact' => 'numeric',
        'status' => 'required|in:draft,submitted,approved,completed',
        'approved_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'adjustment_number.required' => 'Adjustment number is required',
        'adjustment_number.unique' => 'This adjustment number is already in use',
        'warehouse_id.required' => 'Warehouse is required',
        'warehouse_id.exists' => 'Selected warehouse does not exist',
        'adjustment_date.required' => 'Adjustment date is required',
        'reason_code.required' => 'Reason code is required',
        'reason_code.in' => 'Invalid reason code',
        'status.required' => 'Status is required',
        'status.in' => 'Invalid status',
        'approved_by.exists' => 'Selected approver does not exist'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'adjustment_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'total_value_impact' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse' => [
            Warehouse::class
        ],
        'approver' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'approved_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'items' => [
            StockAdjustmentItem::class,
            'delete' => true
        ]
    ];

    /**
     * @var array morphMany relations for activity tracking
     */
    public $morphMany = [
        // TODO: Activity tracking via Feeder plugin
        // 'feeds' => [\Omsb\Feeder\Models\Feed::class, 'name' => 'feedable']
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
            
            // Default adjustment_date to today if not set
            if (!$model->adjustment_date) {
                $model->adjustment_date = Carbon::today();
            }
        });

        // Prevent modification of non-draft documents
        static::updating(function ($model) {
            if ($model->getOriginal('status') !== 'draft' && $model->isDirty(['warehouse_id', 'reason_code'])) {
                throw new ValidationException([
                    'status' => 'Cannot modify key fields of a non-draft stock adjustment'
                ]);
            }
        });

        // Calculate total value impact on save
        static::saving(function ($model) {
            if ($model->items) {
                $model->total_value_impact = $model->items->sum('value_impact');
            }
        });

        // Prevent deletion of approved/completed documents
        static::deleting(function ($model) {
            if (in_array($model->status, ['approved', 'completed'])) {
                throw new ValidationException([
                    'status' => 'Cannot delete an approved or completed stock adjustment'
                ]);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->adjustment_number,
            $this->adjustment_date->format('d M Y'),
            strtoupper($this->status)
        );
    }

    /**
     * Check if adjustment can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if adjustment can be approved
     */
    public function canApprove(): bool
    {
        return in_array($this->status, ['submitted', 'draft']);
    }

    /**
     * Check if adjustment can be completed
     */
    public function canComplete(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if adjustment is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if adjustment is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if adjustment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if adjustment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Submit for approval
     */
    public function submit(): bool
    {
        if (!$this->isDraft()) {
            throw new ValidationException([
                'status' => 'Only draft adjustments can be submitted'
            ]);
        }

        if ($this->items->isEmpty()) {
            throw new ValidationException([
                'items' => 'Cannot submit an adjustment without line items'
            ]);
        }

        $this->status = 'submitted';
        return $this->save();
    }

    /**
     * Approve the adjustment
     */
    public function approve(int $approvedBy = null): bool
    {
        if (!$this->canApprove()) {
            throw new ValidationException([
                'status' => 'This adjustment cannot be approved'
            ]);
        }

        if ($approvedBy) {
            $this->approved_by = $approvedBy;
        } elseif (BackendAuth::check()) {
            // Auto-set from current user
            $user = BackendAuth::getUser();
            // TODO: Map backend user to staff
            // $this->approved_by = $user->staff_id;
        }

        $this->status = 'approved';
        return $this->save();
    }

    /**
     * Complete the adjustment (finalize and update inventory)
     * 
     * This creates InventoryLedger entries and updates WarehouseItem quantities
     */
    public function complete(): bool
    {
        if (!$this->canComplete()) {
            throw new ValidationException([
                'status' => 'Only approved adjustments can be completed'
            ]);
        }

        // TODO: Create InventoryLedger entries via InventoryLedgerService
        // foreach ($this->items as $item) {
        //     InventoryLedgerService::createAdjustmentEntry([...]);
        // }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Reject the adjustment
     */
    public function reject(string $reason = null): bool
    {
        if ($this->isCompleted()) {
            throw new ValidationException([
                'status' => 'Cannot reject a completed adjustment'
            ]);
        }

        if ($reason) {
            $this->notes = $this->notes 
                ? $this->notes . "\n\nRejection reason: " . $reason 
                : "Rejection reason: " . $reason;
        }

        $this->status = 'draft';
        $this->approved_by = null;
        return $this->save();
    }

    /**
     * Calculate total value impact from items
     */
    public function calculateTotalValueImpact(): float
    {
        return $this->items->sum('value_impact');
    }

    /**
     * Get item count
     */
    public function getItemCountAttribute(): int
    {
        return $this->items->count();
    }

    /**
     * Get total positive adjustments
     */
    public function getTotalPositiveAdjustmentsAttribute(): float
    {
        return $this->items->where('quantity_variance', '>', 0)->sum('quantity_variance');
    }

    /**
     * Get total negative adjustments
     */
    public function getTotalNegativeAdjustmentsAttribute(): float
    {
        return $this->items->where('quantity_variance', '<', 0)->sum('quantity_variance');
    }

    /**
     * Scope: Draft adjustments
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Submitted adjustments
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope: Approved adjustments
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Completed adjustments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: By warehouse
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope: By reason code
     */
    public function scopeByReasonCode($query, string $reasonCode)
    {
        return $query->where('reason_code', $reasonCode);
    }

    /**
     * Scope: By date range
     */
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('adjustment_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Recent adjustments (within days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('adjustment_date', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope: With positive impact (increases)
     */
    public function scopePositiveImpact($query)
    {
        return $query->where('total_value_impact', '>', 0);
    }

    /**
     * Scope: With negative impact (decreases)
     */
    public function scopeNegativeImpact($query)
    {
        return $query->where('total_value_impact', '<', 0);
    }

    /**
     * Get status options for dropdowns
     */
    public function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'completed' => 'Completed'
        ];
    }

    /**
     * Get reason code options for dropdowns
     */
    public function getReasonCodeOptions(): array
    {
        return [
            'damage' => 'Damage',
            'theft' => 'Theft',
            'count_variance' => 'Count Variance',
            'expired' => 'Expired',
            'obsolete' => 'Obsolete',
            'quality_issue' => 'Quality Issue',
            'write_off' => 'Write-off',
            'correction' => 'Correction',
            'other' => 'Other'
        ];
    }
}
