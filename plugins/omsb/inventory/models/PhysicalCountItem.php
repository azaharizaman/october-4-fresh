<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * PhysicalCountItem Model
 * 
 * Individual items counted during physical inventory count.
 * Compares system quantity with physical count and tracks variance.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class PhysicalCountItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_physical_count_items';

    protected $fillable = [
        'physical_count_id', 'warehouse_item_id', 'system_quantity',
        'counted_quantity', 'variance', 'count_uom_id',
        'counted_quantity_in_uom', 'counted_quantity_in_default_uom',
        'conversion_factor_used', 'lot_number', 'serial_numbers',
        'counted_by', 'verified_by', 'count_notes'
    ];

    protected $nullable = ['lot_number', 'serial_numbers', 'counted_by', 'verified_by', 'count_notes'];

    public $rules = [
        'physical_count_id' => 'required|integer|exists:omsb_inventory_physical_counts,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'system_quantity' => 'required|numeric',
        'counted_quantity' => 'required|numeric|min:0',
        'variance' => 'required|numeric',
        'count_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'counted_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'verified_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'system_quantity' => 'decimal:6',
        'counted_quantity' => 'decimal:6',
        'variance' => 'decimal:6',
        'counted_quantity_in_uom' => 'decimal:6',
        'counted_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6',
        'serial_numbers' => 'json'
    ];

    public $belongsTo = [
        'physical_count' => PhysicalCount::class,
        'warehouse_item' => WarehouseItem::class,
        'count_uom' => [UnitOfMeasure::class, 'key' => 'count_uom_id'],
        'counter' => ['Omsb\Organization\Models\Staff', 'key' => 'counted_by'],
        'verifier' => ['Omsb\Organization\Models\Staff', 'key' => 'verified_by']
    ];

    public static function boot(): void
    {
        parent::boot();

        static::saving(function ($model) {
            $model->variance = $model->counted_quantity - $model->system_quantity;
            
            if (!$model->conversion_factor_used && $model->counted_quantity_in_uom > 0) {
                $model->conversion_factor_used = $model->counted_quantity_in_default_uom / $model->counted_quantity_in_uom;
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->warehouse_item ? $this->warehouse_item->display_name : 'Unknown Item';
        return sprintf('%s - Variance: %s', $itemName, $this->variance >= 0 ? '+' . $this->variance : $this->variance);
    }

    public function hasVariance(): bool { return $this->variance != 0; }
    public function isOverage(): bool { return $this->variance > 0; }
    public function isShortage(): bool { return $this->variance < 0; }
    public function getAbsoluteVarianceAttribute(): float { return abs($this->variance); }

    public function getVariancePercentageAttribute(): float
    {
        if ($this->system_quantity == 0) {
            return $this->counted_quantity > 0 ? 100 : 0;
        }
        return ($this->variance / $this->system_quantity) * 100;
    }

    public function scopeWithVariance($query) { return $query->where('variance', '!=', 0); }
    public function scopeOverages($query) { return $query->where('variance', '>', 0); }
    public function scopeShortages($query) { return $query->where('variance', '<', 0); }
}
