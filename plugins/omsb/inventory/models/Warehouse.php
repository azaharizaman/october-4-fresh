<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;

/**
 * Warehouse Model
 * 
 * Represents storage locations within organizational sites.
 * Each site can have multiple warehouses with one designated as receiving warehouse.
 *
 * @property int $id
 * @property string $code Unique warehouse code
 * @property string $name Warehouse name
 * @property string $status Warehouse status (active, inactive, maintenance)
 * @property string $type Warehouse type (main, receiving, picking, quarantine)
 * @property string|null $tel_no Telephone number
 * @property string|null $fax_no Fax number
 * @property bool $is_receiving_warehouse Default receiving warehouse for site
 * @property bool $allows_negative_stock Allow negative stock levels
 * @property string|null $description Additional notes
 * @property float|null $storage_capacity Storage capacity value
 * @property string|null $capacity_unit Capacity unit (sqm, cbm)
 * @property int $site_id Owning site
 * @property int|null $in_charge_person Warehouse manager staff ID
 * @property int|null $address_id Physical address
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Warehouse extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Omsb\Feeder\Traits\HasFeed;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_warehouses';

    /**
     * HasFeed trait configuration
     */
    protected $feedMessageTemplate = '{actor} {action} Warehouse "{name}" ({code})';
    protected $feedableActions = ['created', 'updated', 'deleted', 'activated', 'deactivated', 'maintenance'];
    protected $feedSignificantFields = ['name', 'code', 'status', 'is_receiving_warehouse', 'in_charge_person'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'status',
        'type',
        'tel_no',
        'fax_no',
        'is_receiving_warehouse',
        'allows_negative_stock',
        'description',
        'storage_capacity',
        'capacity_unit',
        'site_id',
        'in_charge_person',
        'address_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'tel_no',
        'fax_no',
        'description',
        'storage_capacity',
        'capacity_unit',
        'in_charge_person',
        'address_id',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_inventory_warehouses,code',
        'name' => 'required|max:255',
        'status' => 'required|in:active,inactive,maintenance',
        'type' => 'required|in:main,receiving,picking,quarantine',
        'site_id' => 'required|integer|exists:omsb_organization_sites,id',
        'in_charge_person' => 'nullable|integer|exists:omsb_organization_staff,id',
        'address_id' => 'nullable|integer|exists:omsb_organization_addresses,id',
        'is_receiving_warehouse' => 'boolean',
        'allows_negative_stock' => 'boolean',
        'storage_capacity' => 'nullable|numeric|min:0',
        'capacity_unit' => 'nullable|in:sqm,cbm'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'code.required' => 'Warehouse code is required',
        'code.unique' => 'This warehouse code is already in use',
        'name.required' => 'Warehouse name is required',
        'status.required' => 'Warehouse status is required',
        'type.required' => 'Warehouse type is required',
        'site_id.required' => 'Site is required',
        'site_id.exists' => 'Selected site does not exist'
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
        'is_receiving_warehouse' => 'boolean',
        'allows_negative_stock' => 'boolean',
        'storage_capacity' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'site' => [
            // TODO: Reference to Organization plugin - Site model
            // This will be available once Organization plugin is fully implemented
            // \Omsb\Organization\Models\Site::class
            'Omsb\Organization\Models\Site'
        ],
        'manager' => [
            // TODO: Reference to Organization plugin - Staff model
            // This will be available once Organization plugin is fully implemented
            // \Omsb\Organization\Models\Staff::class
            'Omsb\Organization\Models\Staff',
            'key' => 'in_charge_person'
        ],
        'address' => [
            // TODO: Reference to Organization plugin - Address model
            // This will be available once Organization plugin is fully implemented
            // \Omsb\Organization\Models\Address::class
            'Omsb\Organization\Models\Address'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'warehouse_items' => [
            WarehouseItem::class,
            'key' => 'warehouse_id'
        ],
        'mrns' => [
            Mrn::class,
            'key' => 'warehouse_id'
        ],
        'mris' => [
            Mri::class,
            'key' => 'warehouse_id'
        ],
        'stock_adjustments' => [
            StockAdjustment::class,
            'key' => 'warehouse_id'
        ],
        'physical_counts' => [
            PhysicalCount::class,
            'key' => 'warehouse_id'
        ],
        'transfers_from' => [
            StockTransfer::class,
            'key' => 'from_warehouse_id'
        ],
        'transfers_to' => [
            StockTransfer::class,
            'key' => 'to_warehouse_id'
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

        // Validate receiving warehouse uniqueness per site
        static::saving(function ($model) {
            if ($model->is_receiving_warehouse && $model->site_id) {
                $existingReceiving = self::where('site_id', $model->site_id)
                    ->where('is_receiving_warehouse', true)
                    ->where('id', '!=', $model->id ?? 0)
                    ->where('status', 'active')
                    ->first();

                if ($existingReceiving) {
                    throw new \ValidationException([
                        'is_receiving_warehouse' => 'Site already has a receiving warehouse: ' . $existingReceiving->name
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
     * Get full display with site information
     */
    public function getFullDisplayAttribute(): string
    {
        $display = $this->display_name;
        if ($this->site) {
            $display .= ' (' . $this->site->name . ')';
        }
        return $display;
    }

    /**
     * Scope: Active warehouses only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter by site
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Receiving warehouses only
     */
    public function scopeReceiving($query)
    {
        return $query->where('is_receiving_warehouse', true);
    }

    /**
     * Get total items count in warehouse
     */
    public function getTotalItemsCountAttribute(): int
    {
        return $this->warehouse_items()
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get total quantity on hand (all items)
     */
    public function getTotalQuantityOnHandAttribute(): float
    {
        return $this->warehouse_items()
            ->where('is_active', true)
            ->sum('quantity_on_hand');
    }

    /**
     * Get total reserved quantity (all items)
     */
    public function getTotalQuantityReservedAttribute(): float
    {
        return $this->warehouse_items()
            ->where('is_active', true)
            ->sum('quantity_reserved');
    }

    /**
     * Get site options for dropdown
     * 
     * TODO: This references Organization plugin's Site model
     * Implementation assumes Site::class exists with required methods
     */
    public function getSiteIdOptions(): array
    {
        // TODO: Organization plugin reference
        // return \Omsb\Organization\Models\Site::active()
        //     ->orderBy('name')
        //     ->pluck('display_name', 'id')
        //     ->toArray();
        return [];
    }

    /**
     * Get manager options for dropdown
     * 
     * TODO: This references Organization plugin's Staff model
     * Implementation assumes Staff::class exists with required methods
     */
    public function getInChargePersonOptions(): array
    {
        // TODO: Organization plugin reference
        // return \Omsb\Organization\Models\Staff::active()
        //     ->orderBy('name')
        //     ->pluck('display_name', 'id')
        //     ->toArray();
        return [];
    }

    /**
     * Get address options for dropdown
     * 
     * TODO: This references Organization plugin's Address model
     * Implementation assumes Address::class exists with required methods
     */
    public function getAddressIdOptions(): array
    {
        // TODO: Organization plugin reference
        // return \Omsb\Organization\Models\Address::active()
        //     ->orderBy('address_city')
        //     ->pluck('full_address', 'id')
        //     ->toArray();
        return [];
    }

    /**
     * Check if warehouse can accept new stock
     */
    public function canAcceptStock(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if warehouse allows negative stock
     */
    public function allowsNegativeStock(): bool
    {
        return $this->allows_negative_stock;
    }
}
