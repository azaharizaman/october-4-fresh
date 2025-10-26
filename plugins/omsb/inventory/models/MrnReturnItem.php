<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * MrnReturnItem Model - MRN Return Line Item
 * 
 * Individual items being returned to vendor.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MrnReturnItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_mrn_return_items';

    protected $fillable = [
        'mrn_return_id', 'mrn_item_id', 'warehouse_item_id',
        'return_quantity', 'unit_cost', 'total_cost', 'return_uom_id',
        'return_quantity_in_uom', 'return_quantity_in_default_uom',
        'conversion_factor_used', 'lot_number', 'remarks'
    ];

    protected $nullable = ['lot_number', 'remarks'];

    public $rules = [
        'mrn_return_id' => 'required|integer|exists:omsb_inventory_mrn_returns,id',
        'mrn_item_id' => 'nullable|integer|exists:omsb_inventory_mrn_items,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'return_quantity' => 'required|numeric|min:0.000001',
        'unit_cost' => 'required|numeric|min:0',
        'total_cost' => 'required|numeric|min:0',
        'return_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'return_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'return_quantity_in_uom' => 'decimal:6',
        'return_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6'
    ];

    public $belongsTo = [
        'mrn_return' => MrnReturn::class,
        'mrn_item' => MrnItem::class,
        'warehouse_item' => WarehouseItem::class,
        'return_uom' => [UnitOfMeasure::class, 'key' => 'return_uom_id']
    ];

    public static function boot(): void
    {
        parent::boot();
        static::saving(function ($model) {
            $model->total_cost = $model->return_quantity * $model->unit_cost;
        });
    }
}
