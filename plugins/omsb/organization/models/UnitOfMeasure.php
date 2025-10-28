<?php namespace Omsb\Organization\Models;

use Model;
use BackendAuth;

/**
 * UnitOfMeasure Model (Organization-Level)
 * 
 * Central management of units of measure across the entire organization.
 * Supports base UOM normalization for data integrity and multi-level conversions.
 *
 * @property int $id
 * @property string $code Unique code (ROLL, PACK, BOX, DRUM, EA, KG, etc.)
 * @property string $name Display name (Roll, Pack of 6, Box, Drum, Each, Kilogram, etc.)
 * @property string|null $symbol Symbol notation (pcs, kg, m, etc.)
 * @property string $uom_type Type category (count, weight, volume, length, area)
 * @property int|null $base_uom_id Self-reference to base UOM (null if this IS the base)
 * @property float|null $conversion_to_base_factor Factor to convert to base (e.g., 1 Box = 12 Rolls, factor = 12)
 * @property bool $for_purchase Available for procurement transactions
 * @property bool $for_inventory Available for inventory transactions
 * @property bool $is_approved Organizational approval flag
 * @property bool $is_active Active status
 * @property int|null $decimal_places Precision for this UOM (e.g., 0 for count, 2 for weight)
 * @property string|null $description Additional notes
 * @property int|null $created_by Backend user who created this
 * @property int|null $approved_by Backend user who approved this
 * @property \Carbon\Carbon|null $approved_at Approval timestamp
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
    public $table = 'omsb_organization_unit_of_measures';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'uom_type',
        'base_uom_id',
        'conversion_to_base_factor',
        'for_purchase',
        'for_inventory',
        'is_approved',
        'is_active',
        'decimal_places',
        'description'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'symbol',
        'base_uom_id',
        'conversion_to_base_factor',
        'decimal_places',
        'description',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:10|unique:omsb_organization_unit_of_measures,code',
        'name' => 'required|max:255',
        'symbol' => 'nullable|max:10',
        'uom_type' => 'required|in:count,weight,volume,length,area',
        'base_uom_id' => 'nullable|integer|exists:omsb_organization_unit_of_measures,id',
        'conversion_to_base_factor' => 'nullable|numeric|min:0.000001|required_with:base_uom_id',
        'for_purchase' => 'boolean',
        'for_inventory' => 'boolean',
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'nullable|integer|min:0|max:6'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'code.required' => 'UOM code is required',
        'code.unique' => 'This UOM code is already in use',
        'name.required' => 'UOM name is required',
        'uom_type.required' => 'UOM type is required',
        'uom_type.in' => 'UOM type must be one of: count, weight, volume, length, area',
        'base_uom_id.exists' => 'Selected base UOM does not exist',
        'conversion_to_base_factor.required_with' => 'Conversion factor is required when base UOM is specified',
        'conversion_to_base_factor.min' => 'Conversion factor must be greater than zero'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'approved_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'conversion_to_base_factor' => 'float',
        'for_purchase' => 'boolean',
        'for_inventory' => 'boolean',
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer'
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
        'child_uoms' => [
            self::class,
            'key' => 'base_uom_id'
        ]
    ];

    /**
     * @var array BelongsTo relations
     */
    public $belongsTo = [
        'base_uom' => [
            self::class,
            'key' => 'base_uom_id'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ],
        'approver' => [
            \Backend\Models\User::class,
            'key' => 'approved_by'
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

        // Validate circular reference prevention
        static::saving(function ($model) {
            if ($model->base_uom_id) {
                // Prevent self-reference
                if ($model->base_uom_id === $model->id) {
                    throw new \ValidationException(['base_uom_id' => 'UOM cannot reference itself as base']);
                }

                // Prevent circular references (A -> B -> A)
                $checkUom = $model->base_uom;
                $visited = [$model->id];
                
                while ($checkUom && $checkUom->base_uom_id) {
                    if (in_array($checkUom->base_uom_id, $visited)) {
                        throw new \ValidationException(['base_uom_id' => 'Circular reference detected in UOM hierarchy']);
                    }
                    $visited[] = $checkUom->id;
                    $checkUom = $checkUom->base_uom;
                }
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
        if ($this->base_uom_id) {
            $display .= ' [Base: ' . $this->base_uom->code . ']';
        }
        return $display;
    }

    /**
     * Check if this is a base UOM (not derived from another)
     */
    public function isBaseUom(): bool
    {
        return $this->base_uom_id === null;
    }

    /**
     * Get the ultimate base UOM (traverse hierarchy)
     */
    public function getUltimateBaseUom(): self
    {
        if ($this->isBaseUom()) {
            return $this;
        }

        $current = $this;
        while ($current->base_uom_id) {
            $current = $current->base_uom;
        }

        return $current;
    }

    /**
     * Normalize quantity to base UOM
     * 
     * @param float $quantity Quantity in this UOM
     * @return float Quantity in base UOM
     */
    public function normalizeToBase(float $quantity): float
    {
        if ($this->isBaseUom()) {
            return $quantity;
        }

        // Traverse up to base, multiplying factors
        $normalized = $quantity;
        $current = $this;

        while ($current->base_uom_id) {
            $normalized *= $current->conversion_to_base_factor;
            $current = $current->base_uom;
        }

        return round($normalized, $this->getUltimateBaseUom()->decimal_places ?? 6);
    }

    /**
     * Denormalize quantity from base UOM to this UOM
     * 
     * @param float $baseQuantity Quantity in base UOM
     * @return float Quantity in this UOM
     */
    public function denormalizeFromBase(float $baseQuantity): float
    {
        if ($this->isBaseUom()) {
            return $baseQuantity;
        }

        // Traverse up to base, collecting factors
        $totalFactor = 1;
        $current = $this;

        while ($current->base_uom_id) {
            $totalFactor *= $current->conversion_to_base_factor;
            $current = $current->base_uom;
        }

        return round($baseQuantity / $totalFactor, $this->decimal_places ?? 6);
    }

    /**
     * Convert quantity from this UOM to another UOM
     * Uses base UOM as intermediary
     * 
     * @param float $quantity Quantity in this UOM
     * @param UnitOfMeasure $toUom Target UOM
     * @return float|null Converted quantity, or null if incompatible UOMs
     */
    public function convertQuantityTo(float $quantity, UnitOfMeasure $toUom): ?float
    {
        // Same UOM, no conversion needed
        if ($this->id === $toUom->id) {
            return $quantity;
        }

        // Check if both UOMs share the same ultimate base
        $thisBase = $this->getUltimateBaseUom();
        $toBase = $toUom->getUltimateBaseUom();

        if ($thisBase->id !== $toBase->id) {
            // Incompatible UOMs (different base units)
            return null;
        }

        // Convert: this -> base -> target
        $baseQuantity = $this->normalizeToBase($quantity);
        return $toUom->denormalizeFromBase($baseQuantity);
    }

    /**
     * Get conversion factor to another UOM (via base)
     * 
     * @param UnitOfMeasure $toUom Target UOM
     * @return float|null Conversion factor, or null if incompatible
     */
    public function getConversionFactorTo(UnitOfMeasure $toUom): ?float
    {
        if ($this->id === $toUom->id) {
            return 1.0;
        }

        // Check compatibility
        $thisBase = $this->getUltimateBaseUom();
        $toBase = $toUom->getUltimateBaseUom();

        if ($thisBase->id !== $toBase->id) {
            return null;
        }

        // Factor = (this -> base) / (target -> base)
        $thisToBase = 1;
        $current = $this;
        while ($current->base_uom_id) {
            $thisToBase *= $current->conversion_to_base_factor;
            $current = $current->base_uom;
        }

        $toToBase = 1;
        $current = $toUom;
        while ($current->base_uom_id) {
            $toToBase *= $current->conversion_to_base_factor;
            $current = $current->base_uom;
        }

        return $thisToBase / $toToBase;
    }

    /**
     * Scope: Active and approved units only
     */
    public function scopeActiveApproved($query)
    {
        return $query->where('is_active', true)
                     ->where('is_approved', true);
    }

    /**
     * Scope: Units available for purchase
     */
    public function scopeForPurchase($query)
    {
        return $query->where('for_purchase', true)
                     ->where('is_active', true)
                     ->where('is_approved', true);
    }

    /**
     * Scope: Units available for inventory
     */
    public function scopeForInventory($query)
    {
        return $query->where('for_inventory', true)
                     ->where('is_active', true)
                     ->where('is_approved', true);
    }

    /**
     * Scope: Base UOMs only (not derived)
     */
    public function scopeBaseOnly($query)
    {
        return $query->whereNull('base_uom_id');
    }

    /**
     * Scope: Filter by UOM type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('uom_type', $type);
    }
}
