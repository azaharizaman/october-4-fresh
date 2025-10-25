<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;

/**
 * WarehouseItemUOM Model
 * 
 * Links warehouse items to multiple units of measure.
 * Enables multi-UOM transactions and physical counting.
 *
 * @property int $id
 * @property int $warehouse_item_id Parent warehouse item
 * @property int $uom_id Unit of measure
 * @property bool $is_primary Main UOM for this warehouse item
 * @property bool $is_count_enabled Can be used in physical counts
 * @property bool $is_transaction_enabled Can be used in transactions
 * @property float $conversion_to_default_factor Conversion to HQ's default UOM
 * @property int $min_quantity_precision Decimal places allowed
 * @property bool $is_active Active status
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WarehouseItemUOM extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_warehouse_item_uoms';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'warehouse_item_id',
        'uom_id',
        'is_primary',
        'is_count_enabled',
        'is_transaction_enabled',
        'conversion_to_default_factor',
        'min_quantity_precision',
        'is_active'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'conversion_to_default_factor' => 'required|numeric|min:0.000001',
        'min_quantity_precision' => 'integer|min:0|max:6',
        'is_primary' => 'boolean',
        'is_count_enabled' => 'boolean',
        'is_transaction_enabled' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'warehouse_item_id.required' => 'Warehouse item is required',
        'uom_id.required' => 'Unit of measure is required',
        'conversion_to_default_factor.required' => 'Conversion factor is required',
        'conversion_to_default_factor.min' => 'Conversion factor must be greater than 0'
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
        'is_primary' => 'boolean',
        'is_count_enabled' => 'boolean',
        'is_transaction_enabled' => 'boolean',
        'is_active' => 'boolean',
        'conversion_to_default_factor' => 'decimal:6',
        'min_quantity_precision' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'uom' => [
            UnitOfMeasure::class
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
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

        // Validate primary UOM uniqueness per warehouse item
        static::saving(function ($model) {
            if ($model->is_primary && $model->warehouse_item_id) {
                $existingPrimary = self::where('warehouse_item_id', $model->warehouse_item_id)
                    ->where('is_primary', true)
                    ->where('id', '!=', $model->id ?? 0)
                    ->where('is_active', true)
                    ->first();

                if ($existingPrimary) {
                    throw new \ValidationException([
                        'is_primary' => 'This warehouse item already has a primary UOM: ' . $existingPrimary->uom->name
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
        $display = $this->uom ? $this->uom->code : 'UOM #' . $this->uom_id;
        
        if ($this->is_primary) {
            $display .= ' (Primary)';
        }
        
        return $display;
    }

    /**
     * Scope: Active UOMs only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Primary UOM only
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: Transaction-enabled UOMs
     */
    public function scopeTransactionEnabled($query)
    {
        return $query->where('is_transaction_enabled', true);
    }

    /**
     * Scope: Count-enabled UOMs
     */
    public function scopeCountEnabled($query)
    {
        return $query->where('is_count_enabled', true);
    }

    /**
     * Convert quantity from this UOM to default UOM
     * 
     * @param float $quantity Quantity in this UOM
     * @return float Quantity in default UOM
     */
    public function convertToDefault(float $quantity): float
    {
        return $quantity * $this->conversion_to_default_factor;
    }

    /**
     * Convert quantity from default UOM to this UOM
     * 
     * @param float $quantity Quantity in default UOM
     * @return float Quantity in this UOM
     */
    public function convertFromDefault(float $quantity): float
    {
        return $quantity / $this->conversion_to_default_factor;
    }

    /**
     * Round quantity based on precision settings
     * 
     * @param float $quantity Raw quantity
     * @return float Rounded quantity
     */
    public function roundQuantity(float $quantity): float
    {
        return round($quantity, $this->min_quantity_precision);
    }

    /**
     * Get warehouse item options for dropdown
     */
    public function getWarehouseItemIdOptions(): array
    {
        return WarehouseItem::active()
            ->with(['warehouse', 'purchaseable_item'])
            ->get()
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get UOM options for dropdown
     */
    public function getUomIdOptions(): array
    {
        return UnitOfMeasure::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
