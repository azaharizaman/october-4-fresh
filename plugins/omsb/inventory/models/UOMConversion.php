<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * UOMConversion Model
 * 
 * Defines conversion rules between units of measure.
 * Example: 1 Box = 24 Rolls (conversion_factor = 24)
 *
 * @property int $id
 * @property int $from_uom_id Source UOM
 * @property int $to_uom_id Target UOM
 * @property float $conversion_factor Multiplier (from_qty * factor = to_qty)
 * @property bool $is_bidirectional Can convert in both directions
 * @property \Carbon\Carbon|null $effective_from Start date for conversion
 * @property \Carbon\Carbon|null $effective_to End date for conversion
 * @property string|null $notes Conversion description
 * @property bool $is_active Active status
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class UOMConversion extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_uom_conversions';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
        'is_bidirectional',
        'effective_from',
        'effective_to',
        'notes',
        'is_active'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'effective_from',
        'effective_to',
        'notes',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'from_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'to_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id|different:from_uom_id',
        'conversion_factor' => 'required|numeric|min:0.000001',
        'is_bidirectional' => 'boolean',
        'effective_from' => 'nullable|date',
        'effective_to' => 'nullable|date|after_or_equal:effective_from',
        'is_active' => 'boolean'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'from_uom_id.required' => 'Source UOM is required',
        'from_uom_id.exists' => 'Selected source UOM does not exist',
        'to_uom_id.required' => 'Target UOM is required',
        'to_uom_id.exists' => 'Selected target UOM does not exist',
        'to_uom_id.different' => 'Source and target UOM must be different',
        'conversion_factor.required' => 'Conversion factor is required',
        'conversion_factor.numeric' => 'Conversion factor must be a number',
        'conversion_factor.min' => 'Conversion factor must be greater than 0',
        'effective_to.after_or_equal' => 'Effective end date must be after or equal to start date'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'effective_from',
        'effective_to',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_bidirectional' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'from_uom' => [
            UnitOfMeasure::class,
            'key' => 'from_uom_id'
        ],
        'to_uom' => [
            UnitOfMeasure::class,
            'key' => 'to_uom_id'
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

        // Validate UOM types match
        static::saving(function ($model) {
            if ($model->from_uom_id && $model->to_uom_id) {
                $fromUom = UnitOfMeasure::find($model->from_uom_id);
                $toUom = UnitOfMeasure::find($model->to_uom_id);

                if ($fromUom && $toUom && $fromUom->uom_type !== $toUom->uom_type) {
                    throw new \ValidationException([
                        'to_uom_id' => 'Cannot convert between different UOM types (' . 
                                       $fromUom->uom_type . ' vs ' . $toUom->uom_type . ')'
                    ]);
                }
            }
        });
    }

    /**
     * Get display name for the conversion
     */
    public function getDisplayNameAttribute(): string
    {
        if (!$this->from_uom || !$this->to_uom) {
            return 'Conversion #' . $this->id;
        }

        return sprintf(
            '1 %s = %s %s',
            $this->from_uom->code,
            $this->conversion_factor,
            $this->to_uom->code
        );
    }

    /**
     * Scope: Active conversions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Effective on a given date
     */
    public function scopeEffectiveOn($query, Carbon $date = null)
    {
        $date = $date ?? Carbon::now();

        return $query->where(function ($q) use ($date) {
            $q->where(function ($subQ) use ($date) {
                $subQ->whereNull('effective_from')
                     ->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($subQ) use ($date) {
                $subQ->whereNull('effective_to')
                     ->orWhere('effective_to', '>=', $date);
            });
        });
    }

    /**
     * Scope: Conversions from a specific UOM
     */
    public function scopeFromUom($query, int $uomId)
    {
        return $query->where('from_uom_id', $uomId);
    }

    /**
     * Scope: Conversions to a specific UOM
     */
    public function scopeToUom($query, int $uomId)
    {
        return $query->where('to_uom_id', $uomId);
    }

    /**
     * Convert a quantity using this conversion
     * 
     * @param float $quantity Quantity in from_uom
     * @return float Quantity in to_uom
     */
    public function convert(float $quantity): float
    {
        return $quantity * $this->conversion_factor;
    }

    /**
     * Convert a quantity in reverse (from to_uom to from_uom)
     * Only works if is_bidirectional is true
     * 
     * @param float $quantity Quantity in to_uom
     * @return float Quantity in from_uom
     * @throws \Exception if conversion is not bidirectional
     */
    public function reverseConvert(float $quantity): float
    {
        if (!$this->is_bidirectional) {
            throw new \Exception('This conversion is not bidirectional');
        }

        return $quantity / $this->conversion_factor;
    }

    /**
     * Check if conversion is currently effective
     */
    public function isEffective(Carbon $date = null): bool
    {
        $date = $date ?? Carbon::now();

        $afterStart = !$this->effective_from || $date->greaterThanOrEqualTo($this->effective_from);
        $beforeEnd = !$this->effective_to || $date->lessThanOrEqualTo($this->effective_to);

        return $this->is_active && $afterStart && $beforeEnd;
    }

    /**
     * Get from UOM options for dropdown
     */
    public function getFromUomIdOptions(): array
    {
        return UnitOfMeasure::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get to UOM options for dropdown
     */
    public function getToUomIdOptions(): array
    {
        return UnitOfMeasure::active()
            ->orderBy('code')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
