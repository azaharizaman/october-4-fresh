<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * InventoryValuationItem Model
 * 
 * Individual item valuations within a valuation report.
 * Tracks quantity, cost, and calculated valuation amount.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class InventoryValuationItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_inventory_valuation_items';

    protected $fillable = [
        'inventory_valuation_id', 'warehouse_item_id', 'quantity_on_hand',
        'unit_cost', 'valuation_amount', 'cost_layers'
    ];

    protected $nullable = ['cost_layers'];

    public $rules = [
        'inventory_valuation_id' => 'required|integer|exists:omsb_inventory_inventory_valuations,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'quantity_on_hand' => 'required|numeric|min:0',
        'unit_cost' => 'required|numeric|min:0',
        'valuation_amount' => 'required|numeric|min:0'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'quantity_on_hand' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'valuation_amount' => 'decimal:2',
        'cost_layers' => 'json'
    ];

    public $belongsTo = [
        'inventory_valuation' => InventoryValuation::class,
        'warehouse_item' => WarehouseItem::class
    ];

    public static function boot(): void
    {
        parent::boot();
        static::saving(function ($model) {
            $model->valuation_amount = $model->quantity_on_hand * $model->unit_cost;
        });
    }

    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->warehouse_item ? $this->warehouse_item->display_name : 'Unknown Item';
        return sprintf('%s - Qty: %s @ %s', $itemName, $this->quantity_on_hand, $this->unit_cost);
    }
}
