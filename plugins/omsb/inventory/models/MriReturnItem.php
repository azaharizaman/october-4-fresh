<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * MriReturnItem Model - MRI Return Line Item
 * 
 * Individual items being returned to warehouse from issuance.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MriReturnItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_mri_return_items';

    protected $fillable = [
        'mri_return_id', 'mri_item_id', 'warehouse_item_id',
        'return_quantity', 'unit_cost', 'total_cost', 'return_uom_id',
        'return_quantity_in_uom', 'return_quantity_in_default_uom',
        'conversion_factor_used', 'lot_number', 'serial_numbers', 'remarks'
    ];

    protected $nullable = ['lot_number', 'serial_numbers', 'remarks'];

    public $rules = [
        'mri_return_id' => 'required|integer|exists:omsb_inventory_mri_returns,id',
        'mri_item_id' => 'nullable|integer|exists:omsb_inventory_mri_items,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'return_quantity' => 'required|numeric|min:0.000001',
        'unit_cost' => 'required|numeric|min:0',
        'return_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'return_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'return_quantity_in_uom' => 'decimal:6',
        'return_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6',
        'serial_numbers' => 'json'
    ];

    public $belongsTo = [
        'mri_return' => MriReturn::class,
        'mri_item' => MriItem::class,
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
