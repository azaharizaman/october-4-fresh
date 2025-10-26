<?php namespace Omsb\Inventory\Models;

use Model;
use ValidationException;

/**
 * StockAdjustmentItem Model - Stock Adjustment Line Item
 * 
 * Individual line items in a stock adjustment document.
 * Tracks before/after quantities and calculates variance.
 * Supports multi-UOM tracking with conversion factors.
 *
 * @property int $id
 * @property int $stock_adjustment_id Parent adjustment document
 * @property int $warehouse_item_id SKU being adjusted
 * @property float $quantity_before System quantity before adjustment
 * @property float $quantity_after Physical/corrected quantity
 * @property float $quantity_variance Difference (after - before)
 * @property float $unit_cost Cost per unit for valuation
 * @property float $value_impact Financial impact (variance × cost)
 * @property int $adjustment_uom_id UOM used for adjustment
 * @property float $quantity_variance_in_uom Variance in adjustment UOM
 * @property float $quantity_variance_in_default_uom Converted to default UOM
 * @property float $conversion_factor_used Conversion audit trail
 * @property string|null $reason_notes Item-specific notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class StockAdjustmentItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_stock_adjustment_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'stock_adjustment_id',
        'warehouse_item_id',
        'quantity_before',
        'quantity_after',
        'quantity_variance',
        'unit_cost',
        'value_impact',
        'adjustment_uom_id',
        'quantity_variance_in_uom',
        'quantity_variance_in_default_uom',
        'conversion_factor_used',
        'reason_notes'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'reason_notes'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'stock_adjustment_id' => 'required|integer|exists:omsb_inventory_stock_adjustments,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'quantity_before' => 'required|numeric',
        'quantity_after' => 'required|numeric|min:0',
        'quantity_variance' => 'required|numeric',
        'unit_cost' => 'required|numeric|min:0',
        'value_impact' => 'required|numeric',
        'adjustment_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'quantity_variance_in_uom' => 'required|numeric',
        'quantity_variance_in_default_uom' => 'required|numeric',
        'conversion_factor_used' => 'required|numeric|min:0.000001'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'stock_adjustment_id.required' => 'Stock adjustment is required',
        'stock_adjustment_id.exists' => 'Selected stock adjustment does not exist',
        'warehouse_item_id.required' => 'Warehouse item is required',
        'warehouse_item_id.exists' => 'Selected warehouse item does not exist',
        'quantity_before.required' => 'Quantity before is required',
        'quantity_after.required' => 'Quantity after is required',
        'quantity_after.min' => 'Quantity after cannot be negative',
        'quantity_variance.required' => 'Quantity variance is required',
        'unit_cost.required' => 'Unit cost is required',
        'unit_cost.min' => 'Unit cost cannot be negative',
        'value_impact.required' => 'Value impact is required',
        'adjustment_uom_id.required' => 'Adjustment UOM is required',
        'adjustment_uom_id.exists' => 'Selected UOM does not exist'
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
        'quantity_before' => 'decimal:6',
        'quantity_after' => 'decimal:6',
        'quantity_variance' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'value_impact' => 'decimal:6',
        'quantity_variance_in_uom' => 'decimal:6',
        'quantity_variance_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'stock_adjustment' => [
            StockAdjustment::class
        ],
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'adjustment_uom' => [
            UnitOfMeasure::class,
            'key' => 'adjustment_uom_id'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-calculate variance and value impact before saving
        static::saving(function ($model) {
            // Calculate variance
            $model->quantity_variance = $model->quantity_after - $model->quantity_before;
            
            // Calculate value impact
            $model->value_impact = $model->quantity_variance * $model->unit_cost;
            
            // If conversion factor not set, calculate it
            // Use epsilon for floating-point zero comparison
            $epsilon = 1e-8;
            if (
                !$model->conversion_factor_used &&
                abs($model->quantity_variance_in_uom) > $epsilon
            ) {
                $model->conversion_factor_used = $model->quantity_variance_in_default_uom / $model->quantity_variance_in_uom;
            }
            
            // Ensure variance in default UOM matches calculated variance
            // (allowing for small rounding differences)
            if (abs($model->quantity_variance - $model->quantity_variance_in_default_uom) > 0.001) {
                // If they don't match, use the UOM-converted value as authoritative
                $model->quantity_variance = $model->quantity_variance_in_default_uom;
            }
        });

        // Validate warehouse item allows negative stock if adjustment would cause it
        static::saving(function ($model) {
            $warehouseItem = $model->warehouse_item;
            if ($warehouseItem && !$warehouseItem->warehouse->allows_negative_stock) {
                if ($model->quantity_after < 0) {
                    throw new ValidationException([
                        'quantity_after' => 'Warehouse does not allow negative stock levels'
                    ]);
                }
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->warehouse_item ? $this->warehouse_item->display_name : 'Unknown Item';
        $variance = $this->quantity_variance >= 0 ? '+' . $this->quantity_variance : $this->quantity_variance;
        return sprintf(
            '%s - Variance: %s',
            $itemName,
            $variance
        );
    }

    /**
     * Check if adjustment is an increase
     */
    public function isIncrease(): bool
    {
        return $this->quantity_variance > 0;
    }

    /**
     * Check if adjustment is a decrease
     */
    public function isDecrease(): bool
    {
        return $this->quantity_variance < 0;
    }

    /**
     * Check if there is no variance
     */
    public function hasNoVariance(): bool
    {
        return $this->quantity_variance == 0;
    }

    /**
     * Get absolute variance
     */
    public function getAbsoluteVarianceAttribute(): float
    {
        return abs($this->quantity_variance);
    }

    /**
     * Get variance percentage (relative to before quantity)
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->quantity_before == 0) {
            return $this->quantity_after > 0 ? 100 : 0;
        }
        return ($this->quantity_variance / $this->quantity_before) * 100;
    }

    /**
     * Get absolute value impact
     */
    public function getAbsoluteValueImpactAttribute(): float
    {
        return abs($this->value_impact);
    }

    /**
     * Convert quantity from adjustment UOM to default UOM
     */
    public function convertToDefaultUom(float $quantityInAdjustmentUom): float
    {
        return $quantityInAdjustmentUom * $this->conversion_factor_used;
    }

    /**
     * Convert quantity from default UOM to adjustment UOM
     */
    public function convertFromDefaultUom(float $quantityInDefaultUom): float
    {
        if ($this->conversion_factor_used == 0) {
            return 0;
        }
        return $quantityInDefaultUom / $this->conversion_factor_used;
    }

    /**
     * Get adjustment direction text
     */
    public function getAdjustmentDirectionAttribute(): string
    {
        if ($this->isIncrease()) {
            return 'Increase';
        } elseif ($this->isDecrease()) {
            return 'Decrease';
        } else {
            return 'No Change';
        }
    }

    /**
     * Scope: Increases only
     */
    public function scopeIncreases($query)
    {
        return $query->where('quantity_variance', '>', 0);
    }

    /**
     * Scope: Decreases only
     */
    public function scopeDecreases($query)
    {
        return $query->where('quantity_variance', '<', 0);
    }

    /**
     * Scope: No variance
     */
    public function scopeNoVariance($query)
    {
        return $query->where('quantity_variance', 0);
    }

    /**
     * Scope: Significant variance (absolute value > threshold)
     */
    public function scopeSignificantVariance($query, float $threshold = 10)
    {
        return $query->whereRaw('ABS(quantity_variance) > ?', [$threshold]);
    }

    /**
     * Scope: Positive value impact
     */
    public function scopePositiveImpact($query)
    {
        return $query->where('value_impact', '>', 0);
    }

    /**
     * Scope: Negative value impact
     */
    public function scopeNegativeImpact($query)
    {
        return $query->where('value_impact', '<', 0);
    }

    /**
     * Scope: By stock adjustment
     */
    public function scopeByStockAdjustment($query, int $stockAdjustmentId)
    {
        return $query->where('stock_adjustment_id', $stockAdjustmentId);
    }

    /**
     * Scope: By warehouse item
     */
    public function scopeByWarehouseItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }
}
