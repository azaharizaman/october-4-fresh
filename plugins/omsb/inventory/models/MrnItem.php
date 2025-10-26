<?php namespace Omsb\Inventory\Models;

use Model;
use ValidationException;

/**
 * MrnItem Model - Material Received Note Line Item
 * 
 * Individual line items in a Material Received Note.
 * Tracks ordered, delivered, received, and rejected quantities.
 * Supports multi-UOM tracking with conversion factors.
 *
 * @property int $id
 * @property int $mrn_id Parent MRN document
 * @property int $warehouse_item_id SKU being received
 * @property int|null $purchase_order_item_id Source PO line item
 * @property float $ordered_quantity Quantity from PO
 * @property float $delivered_quantity What vendor delivered
 * @property float $received_quantity What warehouse accepted
 * @property float $rejected_quantity Damaged/incorrect items
 * @property string|null $rejection_reason Why items were rejected
 * @property float $unit_cost Cost per unit
 * @property float $total_cost Received quantity × unit cost
 * @property int $received_uom_id UOM used for receipt
 * @property float $received_quantity_in_uom Quantity in received UOM
 * @property float $received_quantity_in_default_uom Converted to default UOM
 * @property float $conversion_factor_used Conversion audit trail
 * @property string|null $lot_number Lot/batch number if applicable
 * @property \Carbon\Carbon|null $expiry_date Expiry date if applicable
 * @property string|null $remarks Additional notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MrnItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mrn_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'mrn_id',
        'warehouse_item_id',
        'purchase_order_item_id',
        'ordered_quantity',
        'delivered_quantity',
        'received_quantity',
        'rejected_quantity',
        'rejection_reason',
        'unit_cost',
        'total_cost',
        'received_uom_id',
        'received_quantity_in_uom',
        'received_quantity_in_default_uom',
        'conversion_factor_used',
        'lot_number',
        'expiry_date',
        'remarks'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'purchase_order_item_id',
        'rejection_reason',
        'lot_number',
        'expiry_date',
        'remarks'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'mrn_id' => 'required|integer|exists:omsb_inventory_mrns,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'purchase_order_item_id' => 'nullable|integer|exists:omsb_procurement_purchase_order_items,id',
        'ordered_quantity' => 'required|numeric|min:0',
        'delivered_quantity' => 'required|numeric|min:0',
        'received_quantity' => 'required|numeric|min:0',
        'rejected_quantity' => 'numeric|min:0',
        'unit_cost' => 'required|numeric|min:0',
        'total_cost' => 'required|numeric|min:0',
        'received_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'received_quantity_in_uom' => 'required|numeric|min:0',
        'received_quantity_in_default_uom' => 'required|numeric|min:0',
        'conversion_factor_used' => 'required|numeric|min:0.000001',
        'lot_number' => 'nullable|max:255',
        'expiry_date' => 'nullable|date|after:today',
        'rejection_reason' => 'nullable|max:1000'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'mrn_id.required' => 'MRN is required',
        'mrn_id.exists' => 'Selected MRN does not exist',
        'warehouse_item_id.required' => 'Warehouse item is required',
        'warehouse_item_id.exists' => 'Selected warehouse item does not exist',
        'ordered_quantity.required' => 'Ordered quantity is required',
        'ordered_quantity.min' => 'Ordered quantity cannot be negative',
        'delivered_quantity.required' => 'Delivered quantity is required',
        'delivered_quantity.min' => 'Delivered quantity cannot be negative',
        'received_quantity.required' => 'Received quantity is required',
        'received_quantity.min' => 'Received quantity cannot be negative',
        'rejected_quantity.min' => 'Rejected quantity cannot be negative',
        'unit_cost.required' => 'Unit cost is required',
        'unit_cost.min' => 'Unit cost cannot be negative',
        'total_cost.required' => 'Total cost is required',
        'total_cost.min' => 'Total cost cannot be negative',
        'received_uom_id.required' => 'Received UOM is required',
        'received_uom_id.exists' => 'Selected UOM does not exist',
        'expiry_date.after' => 'Expiry date must be in the future'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'expiry_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'ordered_quantity' => 'decimal:6',
        'delivered_quantity' => 'decimal:6',
        'received_quantity' => 'decimal:6',
        'rejected_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'received_quantity_in_uom' => 'decimal:6',
        'received_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'mrn' => [
            Mrn::class
        ],
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'purchase_order_item' => [
            // TODO: Reference to Procurement plugin - PurchaseOrderItem model
            'Omsb\Procurement\Models\PurchaseOrderItem'
        ],
        'received_uom' => [
            UnitOfMeasure::class,
            'key' => 'received_uom_id'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-calculate total cost before saving
        static::saving(function ($model) {
            // Calculate total cost
            $model->total_cost = $model->received_quantity * $model->unit_cost;
            
            // Validate delivered >= received + rejected
            if ($model->delivered_quantity < ($model->received_quantity + $model->rejected_quantity)) {
                throw new ValidationException([
                    'delivered_quantity' => 'Delivered quantity must be at least the sum of received and rejected quantities'
                ]);
            }
            
            // If conversion factor not set, calculate it
            if (
                !$model->conversion_factor_used
                && $model->received_quantity_in_uom !== null
                && $model->received_quantity_in_uom > 0
            ) {
                $model->conversion_factor_used = $model->received_quantity_in_default_uom / $model->received_quantity_in_uom;
            }
        });

        // Validate rejection reason if rejected quantity > 0
        static::saving(function ($model) {
            if ($model->rejected_quantity > 0 && empty($model->rejection_reason)) {
                throw new ValidationException([
                    'rejection_reason' => 'Rejection reason is required when rejected quantity is greater than zero'
                ]);
            }
        });

        // Create lot/batch record if lot number provided
        static::created(function ($model) {
            if ($model->lot_number && $model->warehouse_item->lot_tracking_enabled) {
                // TODO: Create LotBatch record
                // LotBatch::createFromMrn($model);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->warehouse_item ? $this->warehouse_item->display_name : 'Unknown Item';
        return sprintf(
            '%s - Qty: %s',
            $itemName,
            $this->received_quantity
        );
    }

    /**
     * Calculate total cost (can be called manually)
     */
    public function calculateTotalCost(): float
    {
        return $this->received_quantity * $this->unit_cost;
    }

    /**
     * Get variance between ordered and received
     */
    public function getVarianceAttribute(): float
    {
        return $this->received_quantity - $this->ordered_quantity;
    }

    /**
     * Get variance percentage
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->ordered_quantity === 0) {
            return 0;
        }
        return ($this->variance / $this->ordered_quantity) * 100;
    }

    /**
     * Check if there is over-delivery
     */
    public function hasOverDelivery(): bool
    {
        return $this->delivered_quantity > $this->ordered_quantity;
    }

    /**
     * Check if there is under-delivery
     */
    public function hasUnderDelivery(): bool
    {
        return $this->delivered_quantity < $this->ordered_quantity;
    }

    /**
     * Check if there are rejections
     */
    public function hasRejections(): bool
    {
        return $this->rejected_quantity > 0;
    }

    /**
     * Get rejection percentage
     */
    public function getRejectionPercentageAttribute(): float
    {
        if ($this->delivered_quantity == 0) {
            return 0;
        }
        return ($this->rejected_quantity / $this->delivered_quantity) * 100;
    }

    /**
     * Convert quantity from received UOM to default UOM
     */
    public function convertToDefaultUom(float $quantityInReceivedUom): float
    {
        return $quantityInReceivedUom * $this->conversion_factor_used;
    }

    /**
     * Convert quantity from default UOM to received UOM
     */
    public function convertFromDefaultUom(float $quantityInDefaultUom): float
    {
        if ($this->conversion_factor_used === 0) {
            return 0;
        }
        return $quantityInDefaultUom / $this->conversion_factor_used;
    }

    /**
     * Scope: Items with rejections
     */
    public function scopeWithRejections($query)
    {
        return $query->where('rejected_quantity', '>', 0);
    }

    /**
     * Scope: Items with over-delivery
     */
    public function scopeOverDelivered($query)
    {
        return $query->whereRaw('delivered_quantity > ordered_quantity');
    }

    /**
     * Scope: Items with under-delivery
     */
    public function scopeUnderDelivered($query)
    {
        return $query->whereRaw('delivered_quantity < ordered_quantity');
    }

    /**
     * Scope: Items with lot tracking
     */
    public function scopeWithLotTracking($query)
    {
        return $query->whereNotNull('lot_number');
    }

    /**
     * Scope: Items expiring soon (within days)
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days));
    }

    /**
     * Scope: By MRN
     */
    public function scopeByMrn($query, int $mrnId)
    {
        return $query->where('mrn_id', $mrnId);
    }

    /**
     * Scope: By warehouse item
     */
    public function scopeByWarehouseItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }
}
