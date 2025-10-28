<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * WarehouseItem Model
 * 
 * Represents warehouse-level SKU inventory records.
 * Links PurchaseableItem (from Procurement) to specific warehouse locations.
 * Tracks quantity on hand, reserved, min/max levels, and costing method.
 *
 * @property int $id
 * @property int $purchaseable_item_id Reference to master item catalog
 * @property int $warehouse_id Warehouse location
 * @property int|null $base_uom_id Base UOM for quantity_on_hand (ALWAYS in base units)
 * @property int|null $display_uom_id Warehouse preference for displaying quantities
 * @property int $default_uom_id HQ's preferred UOM (legacy)
 * @property int $primary_inventory_uom_id Warehouse's main UOM (legacy)
 * @property float $quantity_on_hand Current stock level (ALWAYS in base UOM)
 * @property float $quantity_reserved Allocated but not issued
 * @property float $quantity_available Computed (QoH - Reserved)
 * @property float $minimum_stock_level Reorder point
 * @property float|null $maximum_stock_level Stock ceiling
 * @property string|null $barcode Warehouse-specific barcode
 * @property string|null $bin_location Storage location within warehouse
 * @property bool $serial_tracking_enabled Track by serial numbers
 * @property bool $lot_tracking_enabled Track by lot/batch
 * @property bool $is_active Active status
 * @property \Carbon\Carbon|null $last_counted_at Last physical count date
 * @property string $cost_method Costing method (FIFO, LIFO, Average)
 * @property bool $allows_multiple_uoms Multi-UOM enabled
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WarehouseItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_warehouse_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'purchaseable_item_id',
        'warehouse_id',
        'base_uom_id',
        'display_uom_id',
        'default_uom_id',
        'primary_inventory_uom_id',
        'quantity_on_hand',
        'quantity_reserved',
        'minimum_stock_level',
        'maximum_stock_level',
        'barcode',
        'bin_location',
        'serial_tracking_enabled',
        'lot_tracking_enabled',
        'is_active',
        'last_counted_at',
        'cost_method',
        'allows_multiple_uoms'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'base_uom_id',
        'display_uom_id',
        'maximum_stock_level',
        'barcode',
        'bin_location',
        'last_counted_at',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'purchaseable_item_id' => 'required|integer|exists:omsb_procurement_purchaseable_items,id',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'base_uom_id' => 'nullable|integer|exists:omsb_organization_unit_of_measures,id',
        'display_uom_id' => 'nullable|integer|exists:omsb_organization_unit_of_measures,id',
        'default_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'primary_inventory_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'quantity_on_hand' => 'numeric|min:0',
        'quantity_reserved' => 'numeric|min:0',
        'minimum_stock_level' => 'numeric|min:0',
        'maximum_stock_level' => 'nullable|numeric|min:0',
        'cost_method' => 'required|in:FIFO,LIFO,Average',
        'serial_tracking_enabled' => 'boolean',
        'lot_tracking_enabled' => 'boolean',
        'is_active' => 'boolean',
        'allows_multiple_uoms' => 'boolean',
        'last_counted_at' => 'nullable|date'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'purchaseable_item_id.required' => 'Purchaseable item is required',
        'purchaseable_item_id.exists' => 'Selected purchaseable item does not exist',
        'warehouse_id.required' => 'Warehouse is required',
        'warehouse_id.exists' => 'Selected warehouse does not exist',
        'default_uom_id.required' => 'Default UOM is required',
        'primary_inventory_uom_id.required' => 'Primary inventory UOM is required',
        'cost_method.required' => 'Cost method is required',
        'cost_method.in' => 'Cost method must be FIFO, LIFO, or Average'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'last_counted_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'quantity_on_hand' => 'decimal:6',
        'quantity_reserved' => 'decimal:6',
        'minimum_stock_level' => 'decimal:6',
        'maximum_stock_level' => 'decimal:6',
        'serial_tracking_enabled' => 'boolean',
        'lot_tracking_enabled' => 'boolean',
        'is_active' => 'boolean',
        'allows_multiple_uoms' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'purchaseable_item' => [
            // TODO: Reference to Procurement plugin - PurchaseableItem model
            // This is the master item catalog from Procurement plugin
            // \Omsb\Procurement\Models\PurchaseableItem::class
            'Omsb\Procurement\Models\PurchaseableItem'
        ],
        'warehouse' => [
            Warehouse::class
        ],
        'base_uom' => [
            'Omsb\Organization\Models\UnitOfMeasure',
            'key' => 'base_uom_id'
        ],
        'display_uom' => [
            'Omsb\Organization\Models\UnitOfMeasure',
            'key' => 'display_uom_id'
        ],
        'default_uom' => [
            UnitOfMeasure::class,
            'key' => 'default_uom_id'
        ],
        'primary_inventory_uom' => [
            UnitOfMeasure::class,
            'key' => 'primary_inventory_uom_id'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'ledger_entries' => [
            InventoryLedger::class,
            'key' => 'warehouse_item_id'
        ],
        'lot_batches' => [
            LotBatch::class,
            'key' => 'warehouse_item_id'
        ],
        'serial_numbers' => [
            SerialNumber::class,
            'key' => 'warehouse_item_id'
        ],
        'warehouse_item_uoms' => [
            WarehouseItemUOM::class,
            'key' => 'warehouse_item_id'
        ],
        'stock_reservations' => [
            StockReservation::class,
            'key' => 'warehouse_item_id'
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

        // Validate warehouse-item uniqueness
        static::saving(function ($model) {
            $existing = self::where('warehouse_id', $model->warehouse_id)
                ->where('purchaseable_item_id', $model->purchaseable_item_id)
                ->where('id', '!=', $model->id ?? 0)
                ->first();

            if ($existing) {
                throw new \ValidationException([
                    'purchaseable_item_id' => 'This item is already registered in the selected warehouse'
                ]);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $display = $this->warehouse ? $this->warehouse->code : 'N/A';
        $display .= ' - ';
        
        // TODO: Assumes PurchaseableItem has code and name properties
        if ($this->purchaseable_item) {
            $display .= $this->purchaseable_item->code . ' - ' . $this->purchaseable_item->name;
        } else {
            $display .= 'Item #' . $this->purchaseable_item_id;
        }
        
        return $display;
    }

    /**
     * Scope: Active items only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by warehouse
     */
    public function scopeInWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope: Filter by purchaseable item
     */
    public function scopeForItem($query, int $purchaseableItemId)
    {
        return $query->where('purchaseable_item_id', $purchaseableItemId);
    }

    /**
     * Scope: Below minimum stock level
     */
    public function scopeBelowMinimum($query)
    {
        return $query->whereRaw('quantity_on_hand < minimum_stock_level');
    }

    /**
     * Scope: Above maximum stock level
     */
    public function scopeAboveMaximum($query)
    {
        return $query->whereNotNull('maximum_stock_level')
            ->whereRaw('quantity_on_hand > maximum_stock_level');
    }

    /**
     * Scope: With lot tracking
     */
    public function scopeLotTracked($query)
    {
        return $query->where('lot_tracking_enabled', true);
    }

    /**
     * Scope: With serial tracking
     */
    public function scopeSerialTracked($query)
    {
        return $query->where('serial_tracking_enabled', true);
    }

    /**
     * Get available quantity (QoH - Reserved)
     * Note: This is computed in DB, but also accessible as attribute
     */
    public function getAvailableQuantityAttribute(): float
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }

    /**
     * Check if item is below reorder point
     */
    public function isBelowMinimum(): bool
    {
        return $this->quantity_on_hand < $this->minimum_stock_level;
    }

    /**
     * Check if item is above maximum level
     */
    public function isAboveMaximum(): bool
    {
        return $this->maximum_stock_level !== null && 
               $this->quantity_on_hand > $this->maximum_stock_level;
    }

    /**
     * Check if item has sufficient available quantity
     */
    public function hasSufficientQuantity(float $requiredQty): bool
    {
        return $this->available_quantity >= $requiredQty;
    }

    /**
     * Adjust quantity on hand
     * Note: This should typically be done through InventoryLedger service
     * 
     * @param float $adjustment Positive for increase, negative for decrease
     * @return bool
     */
    /**
     * Adjust quantity on hand
     * Note: This should typically be done through InventoryLedger service
     *
     * @param float $adjustment Positive for increase, negative for decrease
     * @param bool|null $allowsNegativeStock Pass warehouse's allows_negative_stock to avoid N+1 queries
     * @return bool
     */
    public function adjustQuantity(float $adjustment, ?bool $allowsNegativeStock = null): bool
    {
        $newQty = $this->quantity_on_hand + $adjustment;

        // Check if negative stock is allowed
        $negativeAllowed = $allowsNegativeStock;
        if ($negativeAllowed === null) {
            // fallback to relationship (may trigger query)
            $negativeAllowed = $this->warehouse ? $this->warehouse->allows_negative_stock : false;
        }
        if ($newQty < 0 && !$negativeAllowed) {
            return false;
        }

        $this->quantity_on_hand = $newQty;
        return $this->save();
    }

    /**
     * Reserve quantity
     * 
     * @param float $quantity Quantity to reserve
     * @return bool
     */
    public function reserveQuantity(float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        if ($this->available_quantity < $quantity) {
            return false;
        }

        $this->quantity_reserved += $quantity;
        return $this->save();
    }

    /**
     * Release reserved quantity
     * 
     * @param float $quantity Quantity to release
     * @return bool
     */
    public function releaseReservation(float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $newReserved = max(0, $this->quantity_reserved - $quantity);
        $this->quantity_reserved = $newReserved;
        return $this->save();
    }

    /**
     * Get warehouse options for dropdown
     */
    public function getWarehouseIdOptions(): array
    {
        return Warehouse::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get purchaseable item options for dropdown
     * 
     * TODO: This references Procurement plugin's PurchaseableItem model
     * Implementation assumes PurchaseableItem::class exists with required methods
     */
    public function getPurchaseableItemIdOptions(): array
    {
        // TODO: Procurement plugin reference
        // return \Omsb\Procurement\Models\PurchaseableItem::where('is_inventory_item', true)
        //     ->active()
        //     ->orderBy('code')
        //     ->pluck('display_name', 'id')
        //     ->toArray();
        return [];
    }

    /**
     * Get UOM options for dropdown
     */
    public function getDefaultUomIdOptions(): array
    {
        return UnitOfMeasure::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get UOM options for dropdown
     */
    public function getPrimaryInventoryUomIdOptions(): array
    {
        return UnitOfMeasure::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get base UOM options for dropdown (from Organization plugin)
     */
    public function getBaseUomIdOptions(): array
    {
        return \Omsb\Organization\Models\UnitOfMeasure::where('is_active', true)
            ->where('is_approved', true)
            ->where('for_inventory', true)
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get display UOM options for dropdown (from Organization plugin)
     */
    public function getDisplayUomIdOptions(): array
    {
        return \Omsb\Organization\Models\UnitOfMeasure::where('is_active', true)
            ->where('is_approved', true)
            ->where('for_inventory', true)
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
