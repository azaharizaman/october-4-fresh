<?php namespace Omsb\Procurement\Models;

use Model;
use BackendAuth;
use ValidationException;

/**
 * PurchaseableItem Model
 * 
 * Master catalog of all items that can be purchased.
 * Single source of truth for everything that can be purchased.
 *
 * @property int $id
 * @property string $code Unique item code
 * @property string $name Item name
 * @property string|null $description Item description
 * @property string|null $barcode Barcode
 * @property string $unit_of_measure Unit of measure
 * @property bool $is_inventory_item Whether item is tracked in inventory
 * @property string $item_type Asset classification (consumable, equipment, spare_part, asset, service, other)
 * @property float|null $standard_cost Standard cost
 * @property float|null $last_purchase_cost Last purchase cost
 * @property \Carbon\Carbon|null $last_purchase_date Last purchase date
 * @property bool $is_active Active status
 * @property bool $is_discontinued Discontinued status
 * @property string|null $manufacturer Manufacturer name
 * @property string|null $model_number Model number
 * @property string|null $specifications Technical specifications
 * @property int|null $lead_time_days Average lead time in days
 * @property int $minimum_order_quantity Minimum order quantity
 * @property int|null $item_category_id Item category
 * @property int|null $gl_account_id GL account for non-inventory items
 * @property int|null $preferred_vendor_id Preferred vendor
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class PurchaseableItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_purchaseable_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'barcode',
        'unit_of_measure',
        'is_inventory_item',
        'item_type',
        'standard_cost',
        'last_purchase_cost',
        'last_purchase_date',
        'is_active',
        'is_discontinued',
        'manufacturer',
        'model_number',
        'specifications',
        'lead_time_days',
        'minimum_order_quantity',
        'item_category_id',
        'gl_account_id',
        'preferred_vendor_id',
        'service_code'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'description',
        'barcode',
        'standard_cost',
        'last_purchase_cost',
        'last_purchase_date',
        'manufacturer',
        'model_number',
        'specifications',
        'lead_time_days',
        'item_category_id',
        'gl_account_id',
        'preferred_vendor_id',
        'created_by',
        'service_code'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_procurement_purchaseable_items,code',
        'name' => 'required|max:255',
        'unit_of_measure' => 'required|max:255',
        'is_inventory_item' => 'required|boolean',
        'item_type' => 'required|in:consumable,equipment,spare_part,asset,service,other',
        'service_code' => 'nullable|max:10',
        'is_active' => 'boolean',
        'is_discontinued' => 'boolean',
        'standard_cost' => 'nullable|numeric|min:0',
        'last_purchase_cost' => 'nullable|numeric|min:0',
        'lead_time_days' => 'nullable|integer|min:0',
        'minimum_order_quantity' => 'required|integer|min:1',
        'item_category_id' => 'nullable|integer|exists:omsb_procurement_item_categories,id',
        'gl_account_id' => 'nullable|integer|exists:omsb_organization_gl_accounts,id',
        'preferred_vendor_id' => 'nullable|integer|exists:omsb_procurement_vendors,id'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'code.required' => 'Item code is required',
        'code.unique' => 'This item code is already in use',
        'name.required' => 'Item name is required',
        'unit_of_measure.required' => 'Unit of measure is required',
        'is_inventory_item.required' => 'Inventory item flag is required',
        'item_type.required' => 'Item type is required',
        'minimum_order_quantity.min' => 'Minimum order quantity must be at least 1'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'last_purchase_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'is_inventory_item' => 'boolean',
        'is_active' => 'boolean',
        'is_discontinued' => 'boolean',
        'standard_cost' => 'decimal:2',
        'last_purchase_cost' => 'decimal:2',
        'minimum_order_quantity' => 'integer',
        'lead_time_days' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'item_category' => [
            ItemCategory::class
        ],
        'gl_account' => [
            'Omsb\Organization\Models\GlAccount'
        ],
        'preferred_vendor' => [
            Vendor::class,
            'key' => 'preferred_vendor_id'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'warehouse_items' => [
            'Omsb\Inventory\Models\WarehouseItem',
            'key' => 'purchaseable_item_id'
        ],
        'purchase_request_items' => [
            PurchaseRequestItem::class,
            'key' => 'purchaseable_item_id'
        ],
        'vendor_quotation_items' => [
            VendorQuotationItem::class,
            'key' => 'purchaseable_item_id'
        ],
        'purchase_order_items' => [
            PurchaseOrderItem::class,
            'key' => 'purchaseable_item_id'
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

        // Validate is_inventory_item immutability if QoH > 0
        static::updating(function ($model) {
            if ($model->isDirty('is_inventory_item')) {
                // Check combined quantity on hand across all warehouses
                $totalQoH = \Omsb\Inventory\Models\WarehouseItem::where('purchaseable_item_id', $model->id)
                    ->sum('quantity_on_hand');
                
                if ($totalQoH > 0) {
                    throw new ValidationException([
                        'is_inventory_item' => 'Cannot change inventory item flag while item has stock in warehouses (QoH: ' . $totalQoH . ')'
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
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Get display name with UoM
     */
    public function getFullDisplayAttribute(): string
    {
        return $this->display_name . ' (' . $this->unit_of_measure . ')';
    }

    /**
     * Get item type label
     */
    public function getItemTypeLabelAttribute(): string
    {
        $labels = [
            'consumable' => 'Consumable',
            'equipment' => 'Equipment',
            'spare_part' => 'Spare Part',
            'asset' => 'Asset',
            'service' => 'Service',
            'other' => 'Other'
        ];
        
        return $labels[$this->item_type] ?? $this->item_type;
    }

    /**
     * Get total quantity on hand across all warehouses
     */
    public function getTotalQuantityOnHandAttribute(): float
    {
        if (!$this->is_inventory_item) {
            return 0;
        }
        
        return \Omsb\Inventory\Models\WarehouseItem::where('purchaseable_item_id', $this->id)
            ->where('is_active', true)
            ->sum('quantity_on_hand');
    }

    /**
     * Scope: Active items only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('is_discontinued', false);
    }

    /**
     * Scope: Inventory items only
     */
    public function scopeInventoryItems($query)
    {
        return $query->where('is_inventory_item', true);
    }

    /**
     * Scope: Non-inventory items only
     */
    public function scopeNonInventoryItems($query)
    {
        return $query->where('is_inventory_item', false);
    }

    /**
     * Scope: Filter by item type
     */
    public function scopeByItemType($query, string $itemType)
    {
        return $query->where('item_type', $itemType);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('item_category_id', $categoryId);
    }

    /**
     * Check if item is active
     */
    public function isActive(): bool
    {
        return $this->is_active && !$this->is_discontinued;
    }

    /**
     * Check if item is an inventory item
     */
    public function isInventoryItem(): bool
    {
        return $this->is_inventory_item;
    }

    /**
     * Update last purchase information
     */
    public function updateLastPurchase(float $cost, \Carbon\Carbon $date): void
    {
        $this->last_purchase_cost = $cost;
        $this->last_purchase_date = $date;
        $this->save();
    }

    /**
     * Get item category options for dropdown
     */
    public function getItemCategoryIdOptions(): array
    {
        return ItemCategory::active()
            ->orderBy('name')
            ->pluck('full_name', 'id')
            ->toArray();
    }

    /**
     * Get GL account options for dropdown (for non-inventory items)
     */
    public function getGlAccountIdOptions(): array
    {
        return \Omsb\Organization\Models\GlAccount::active()
            ->orderBy('account_code')
            ->get()
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get preferred vendor options for dropdown
     */
    public function getPreferredVendorIdOptions(): array
    {
        return Vendor::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get service details for this item
     */
    public function getServiceAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceByCode($this->service_code);
    }

    /**
     * Get service name
     */
    public function getServiceNameAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceName($this->service_code);
    }

    /**
     * Get service color
     */
    public function getServiceColorAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceColor($this->service_code);
    }

    /**
     * Get service code options for dropdown
     */
    public function getServiceCodeOptions(): array
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceDropdownOptions();
    }

    /**
     * Scope: Filter by service
     */
    public function scopeByService($query, $serviceCode)
    {
        return $query->where('service_code', $serviceCode);
    }

    /**
     * Check if item belongs to specific service
     */
    public function belongsToService($serviceCode)
    {
        return $this->service_code === $serviceCode;
    }

    /**
     * Get items in same service
     */
    public function getSameServiceItems()
    {
        if (!$this->service_code) {
            return collect();
        }

        return self::byService($this->service_code)
            ->where('id', '!=', $this->id)
            ->active()
            ->get();
    }
}
