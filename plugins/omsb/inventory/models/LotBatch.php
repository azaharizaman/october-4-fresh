<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * LotBatch Model
 * 
 * Tracks lot/batch information for items requiring lot tracking.
 * Essential for perishable items, items with expiry dates, and traceability.
 *
 * @property int $id
 * @property int $warehouse_item_id Parent warehouse item
 * @property string $lot_number Lot/Batch identifier
 * @property float $quantity_received Original received quantity
 * @property float $quantity_available Current available quantity
 * @property \Carbon\Carbon|null $received_date When lot was received
 * @property \Carbon\Carbon|null $expiry_date Expiration date
 * @property \Carbon\Carbon|null $manufacture_date Manufacturing date
 * @property string|null $supplier_lot_number Supplier's lot reference
 * @property string $status Status (active, expired, quarantine, issued)
 * @property string|null $notes Additional information
 * @property int|null $purchase_order_line_item_id Origin PO line
 * @property int|null $mrn_line_item_id Receipt record
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class LotBatch extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_lot_batches';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'warehouse_item_id',
        'lot_number',
        'quantity_received',
        'quantity_available',
        'received_date',
        'expiry_date',
        'manufacture_date',
        'supplier_lot_number',
        'status',
        'notes',
        'purchase_order_line_item_id',
        'mrn_line_item_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'received_date',
        'expiry_date',
        'manufacture_date',
        'supplier_lot_number',
        'notes',
        'purchase_order_line_item_id',
        'mrn_line_item_id',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'lot_number' => 'required|max:255',
        'quantity_received' => 'required|numeric|min:0',
        'quantity_available' => 'required|numeric|min:0',
        'status' => 'required|in:active,expired,quarantine,issued',
        'received_date' => 'nullable|date',
        'expiry_date' => 'nullable|date|after:received_date',
        'manufacture_date' => 'nullable|date|before_or_equal:received_date'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'warehouse_item_id.required' => 'Warehouse item is required',
        'lot_number.required' => 'Lot number is required',
        'quantity_received.required' => 'Received quantity is required',
        'status.required' => 'Status is required',
        'expiry_date.after' => 'Expiry date must be after received date',
        'manufacture_date.before_or_equal' => 'Manufacture date must be before or equal to received date'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'received_date',
        'expiry_date',
        'manufacture_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'quantity_received' => 'decimal:6',
        'quantity_available' => 'decimal:6'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse_item' => [
            WarehouseItem::class
        ],
        // TODO: Reference to Procurement plugin - PurchaseOrderLineItem model
        'purchase_order_line_item' => [
            'Omsb\Procurement\Models\PurchaseOrderLineItem',
            'key' => 'purchase_order_line_item_id'
        ],
        // TODO: Reference to MrnItem model (will be implemented later)
        'mrn_line_item' => [
            MrnItem::class,
            'key' => 'mrn_line_item_id'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
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

            // Auto-set received date if not provided
            if (!$model->received_date) {
                $model->received_date = Carbon::now();
            }
        });

        // Auto-update status based on expiry date
        static::saving(function ($model) {
            if ($model->expiry_date && $model->expiry_date->isPast() && $model->status === 'active') {
                $model->status = 'expired';
            }
        });

        // Validate lot number uniqueness per warehouse item
        static::saving(function ($model) {
            $existing = self::where('warehouse_item_id', $model->warehouse_item_id)
                ->where('lot_number', $model->lot_number)
                ->where('id', '!=', $model->id ?? 0)
                ->first();

            if ($existing) {
                throw new \ValidationException([
                    'lot_number' => 'This lot number already exists for this warehouse item'
                ]);
            }
        });
    }

    /**
     * Get display name for the lot
     */
    public function getDisplayNameAttribute(): string
    {
        $display = $this->lot_number;
        
        if ($this->expiry_date) {
            $display .= ' (Exp: ' . $this->expiry_date->format('Y-m-d') . ')';
        }
        
        return $display;
    }

    /**
     * Scope: Active lots only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Expired lots
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope: In quarantine
     */
    public function scopeInQuarantine($query)
    {
        return $query->where('status', 'quarantine');
    }

    /**
     * Scope: Fully issued (no quantity available)
     */
    public function scopeFullyIssued($query)
    {
        return $query->where('quantity_available', '<=', 0);
    }

    /**
     * Scope: With available quantity
     */
    public function scopeAvailable($query)
    {
        return $query->where('quantity_available', '>', 0);
    }

    /**
     * Scope: Expiring soon (within specified days)
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        $futureDate = Carbon::now()->addDays($days);
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $futureDate)
            ->where('expiry_date', '>', Carbon::now())
            ->where('status', 'active');
    }

    /**
     * Scope: Filter by warehouse item
     */
    public function scopeForItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }

    /**
     * Check if lot is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if lot is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        $futureDate = Carbon::now()->addDays($days);
        return $this->expiry_date->between(Carbon::now(), $futureDate);
    }

    /**
     * Check if lot has available quantity
     */
    public function hasAvailableQuantity(): bool
    {
        return $this->quantity_available > 0;
    }

    /**
     * Get issued quantity
     */
    public function getIssuedQuantityAttribute(): float
    {
        return $this->quantity_received - $this->quantity_available;
    }

    /**
     * Get utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->quantity_received === 0.0) {
            return 0;
        }
        
        return ($this->issued_quantity / $this->quantity_received) * 100;
    }

    /**
     * Issue quantity from lot
     * 
     * @param float $quantity Quantity to issue
     * @return bool
     */
    public function issueQuantity(float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        if ($quantity > $this->quantity_available) {
            return false;
        }

        $this->quantity_available -= $quantity;
        
        // Auto-update status if fully issued
        if ($this->quantity_available <= 0) {
            $this->status = 'issued';
        }

        return $this->save();
    }

    /**
     * Return quantity to lot
     * 
     * @param float $quantity Quantity to return
     * @return bool
     */
    public function returnQuantity(float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $this->quantity_available += $quantity;
        
        // Restore active status if was issued
        if ($this->status === 'issued' && $this->quantity_available > 0) {
            $this->status = 'active';
        }

        return $this->save();
    }

    /**
     * Move lot to quarantine
     * 
     * @param string $reason Reason for quarantine
     * @return bool
     */
    public function quarantine(string $reason): bool
    {
        $this->status = 'quarantine';
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . 
                       'QUARANTINED: ' . $reason . ' (' . Carbon::now()->toDateTimeString() . ')';
        
        return $this->save();
    }

    /**
     * Release lot from quarantine
     * 
     * @return bool
     */
    public function releaseFromQuarantine(): bool
    {
        if ($this->status !== 'quarantine') {
            return false;
        }

        // Check if expired
        if ($this->isExpired()) {
            $this->status = 'expired';
        } else {
            $this->status = 'active';
        }

        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . 
                       'Released from quarantine (' . Carbon::now()->toDateTimeString() . ')';
        
        return $this->save();
    }

    /**
     * Get warehouse item options for dropdown
     */
    public function getWarehouseItemIdOptions(): array
    {
        return WarehouseItem::active()
            ->where('lot_tracking_enabled', true)
            ->with(['warehouse', 'purchaseable_item'])
            ->get()
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
