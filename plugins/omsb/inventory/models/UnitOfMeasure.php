<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;

/**
 * UnitOfMeasure Model
 * 
 * Represents units of measure for inventory items (Each, Box, Kilogram, etc.)
 * Supports multi-UOM system with conversion factors between units.
 *
 * @property int $id
 * @property string $code Unique code (EA, BOX, KG, etc.)
 * @property string $name Display name (Each, Box, Kilogram, etc.)
 * @property string|null $symbol Symbol notation (pcs, kg, m, etc.)
 * @property string $uom_type Type category (count, weight, volume, length, area)
 * @property bool $is_base_unit Whether this is the base unit for conversions
 * @property bool $is_active Active status
 * @property string|null $description Additional notes
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class UnitOfMeasure extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_unit_of_measures';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'uom_type',
        'is_base_unit',
        'is_active',
        'description'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'symbol',
        'description',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:10|unique:omsb_inventory_unit_of_measures,code',
        'name' => 'required|max:255',
        'symbol' => 'nullable|max:10',
        'uom_type' => 'required|in:count,weight,volume,length,area',
        'is_base_unit' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'code.required' => 'UOM code is required',
        'code.unique' => 'This UOM code is already in use',
        'name.required' => 'UOM name is required',
        'uom_type.required' => 'UOM type is required',
        'uom_type.in' => 'UOM type must be one of: count, weight, volume, length, area'
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
        'is_base_unit' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'from_conversions' => [
            UOMConversion::class,
            'key' => 'from_uom_id'
        ],
        'to_conversions' => [
            UOMConversion::class,
            'key' => 'to_uom_id'
        ],
        'warehouse_item_uoms' => [
            WarehouseItemUOM::class,
            'key' => 'uom_id'
        ]
    ];

    /**
     * @var array BelongsTo relations
     */
    public $belongsTo = [
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
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $display = $this->code . ' - ' . $this->name;
        if ($this->symbol) {
            $display .= ' (' . $this->symbol . ')';
        }
        return $display;
    }

    /**
     * Scope: Active units only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by UOM type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('uom_type', $type);
    }

    /**
     * Get conversion factor to another UOM
     * 
     * @param UnitOfMeasure $toUom Target UOM
     * @return float|null Conversion factor, or null if no conversion exists
     */
    public function getConversionFactorTo(UnitOfMeasure $toUom): ?float
    {
        // Check direct conversion
        $conversion = UOMConversion::where('from_uom_id', $this->id)
            ->where('to_uom_id', $toUom->id)
            ->where('is_active', true)
            ->first();

        if ($conversion) {
            return $conversion->conversion_factor;
        }

        // Check inverse conversion
        $inverseConversion = UOMConversion::where('from_uom_id', $toUom->id)
            ->where('to_uom_id', $this->id)
            ->where('is_active', true)
            ->where('is_bidirectional', true)
            ->first();

        if ($inverseConversion) {
            return 1 / $inverseConversion->conversion_factor;
        }

        return null;
    }

    /**
     * Convert quantity from this UOM to another UOM
     * 
     * @param float $quantity Quantity in this UOM
     * @param UnitOfMeasure $toUom Target UOM
     * @return float|null Converted quantity, or null if no conversion exists
     */
    public function convertQuantityTo(float $quantity, UnitOfMeasure $toUom): ?float
    {
        if ($this->id === $toUom->id) {
            return $quantity;
        }

        $factor = $this->getConversionFactorTo($toUom);
        
        if ($factor === null) {
            return null;
        }

        return $quantity * $factor;
    }
}
