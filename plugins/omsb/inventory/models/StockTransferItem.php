<?php namespace Omsb\Inventory\Models;

use Model;
use ValidationException;

/**
 * StockTransferItem Model
 * 
 * Line items for inter-warehouse stock transfers.
 * Tracks requested, shipped, and received quantities with multi-UOM support.
 *
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $from_warehouse_item_id Source warehouse item
 * @property int $purchaseable_item_id For destination creation
 * @property float $quantity_requested
 * @property float $quantity_shipped
 * @property float $quantity_received
 * @property float $unit_cost
 * @property float $total_cost
 * @property int $transfer_uom_id
 * @property float $requested_quantity_in_uom
 * @property float $requested_quantity_in_default_uom
 * @property float $shipped_quantity_in_uom
 * @property float $shipped_quantity_in_default_uom
 * @property float $received_quantity_in_uom
 * @property float $received_quantity_in_default_uom
 * @property float $conversion_factor_used
 * @property string|null $lot_number
 * @property array|null $serial_numbers
 * @property string|null $remarks
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class StockTransferItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_stock_transfer_items';

    protected $fillable = [
        'stock_transfer_id', 'from_warehouse_item_id', 'purchaseable_item_id',
        'quantity_requested', 'quantity_shipped', 'quantity_received',
        'unit_cost', 'total_cost', 'transfer_uom_id',
        'requested_quantity_in_uom', 'requested_quantity_in_default_uom',
        'shipped_quantity_in_uom', 'shipped_quantity_in_default_uom',
        'received_quantity_in_uom', 'received_quantity_in_default_uom',
        'conversion_factor_used', 'lot_number', 'serial_numbers', 'remarks'
    ];

    protected $nullable = ['lot_number', 'serial_numbers', 'remarks'];

    public $rules = [
        'stock_transfer_id' => 'required|integer|exists:omsb_inventory_stock_transfers,id',
        'from_warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'purchaseable_item_id' => 'required|integer|exists:omsb_procurement_purchaseable_items,id',
        'quantity_requested' => 'required|numeric|min:0',
        'quantity_shipped' => 'required|numeric|min:0',
        'quantity_received' => 'numeric|min:0',
        'unit_cost' => 'required|numeric|min:0',
        'total_cost' => 'required|numeric|min:0',
        'transfer_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'conversion_factor_used' => 'required|numeric|min:0.000001'
    ];

    public $customMessages = [
        'stock_transfer_id.required' => 'Stock transfer is required',
        'from_warehouse_item_id.required' => 'Source warehouse item is required',
        'purchaseable_item_id.required' => 'Purchaseable item is required',
        'quantity_requested.required' => 'Requested quantity is required',
        'quantity_shipped.required' => 'Shipped quantity is required',
        'unit_cost.required' => 'Unit cost is required',
        'transfer_uom_id.required' => 'Transfer UOM is required'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'quantity_requested' => 'decimal:6',
        'quantity_shipped' => 'decimal:6',
        'quantity_received' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'requested_quantity_in_uom' => 'decimal:6',
        'requested_quantity_in_default_uom' => 'decimal:6',
        'shipped_quantity_in_uom' => 'decimal:6',
        'shipped_quantity_in_default_uom' => 'decimal:6',
        'received_quantity_in_uom' => 'decimal:6',
        'received_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6',
        'serial_numbers' => 'json'
    ];

    public $belongsTo = [
        'stock_transfer' => StockTransfer::class,
        'from_warehouse_item' => [WarehouseItem::class, 'key' => 'from_warehouse_item_id'],
        'purchaseable_item' => ['Omsb\Procurement\Models\PurchaseableItem'],
        'transfer_uom' => [UnitOfMeasure::class, 'key' => 'transfer_uom_id']
    ];

    public static function boot(): void
    {
        parent::boot();

        static::saving(function ($model) {
            $model->total_cost = $model->quantity_shipped * $model->unit_cost;
            
            if (!$model->conversion_factor_used && $model->requested_quantity_in_uom > 0) {
                $model->conversion_factor_used = $model->requested_quantity_in_default_uom / $model->requested_quantity_in_uom;
            }
            
            if ($model->quantity_shipped > $model->quantity_requested) {
                throw new ValidationException(['quantity_shipped' => 'Shipped quantity cannot exceed requested quantity']);
            }
            
            if ($model->quantity_received > $model->quantity_shipped) {
                throw new ValidationException(['quantity_received' => 'Received quantity cannot exceed shipped quantity']);
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->from_warehouse_item ? $this->from_warehouse_item->display_name : 'Unknown Item';
        return sprintf('%s - Qty: %s', $itemName, $this->quantity_shipped);
    }

    public function isFullyReceived(): bool { return $this->quantity_received >= $this->quantity_shipped; }
    public function isPartiallyReceived(): bool { return $this->quantity_received > 0 && $this->quantity_received < $this->quantity_shipped; }
    public function getPendingQuantityAttribute(): float { return max(0, $this->quantity_shipped - $this->quantity_received); }
    public function getVarianceAttribute(): float { return $this->quantity_received - $this->quantity_shipped; }
    public function hasShortage(): bool { return $this->quantity_received < $this->quantity_shipped; }
    public function hasOverage(): bool { return $this->quantity_received > $this->quantity_shipped; }

    public function scopeFullyReceived($query) { return $query->whereRaw('quantity_received >= quantity_shipped'); }
    public function scopePartiallyReceived($query) { return $query->where('quantity_received', '>', 0)->whereRaw('quantity_received < quantity_shipped'); }
    public function scopePending($query) { return $query->whereRaw('quantity_received < quantity_shipped'); }
    public function scopeByStockTransfer($query, int $transferId) { return $query->where('stock_transfer_id', $transferId); }
}
