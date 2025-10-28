<?php namespace Omsb\Organization\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * UOMConversion Model (Organization-Level)
 * 
 * Defines direct conversion rules between units of measure.
 * Complements the base UOM normalization system for complex multi-level conversions.
 *
 * @property int $id
 * @property int $from_uom_id Source UOM
 * @property int $to_uom_id Target UOM
 * @property float $conversion_factor Multiplier (from_qty * factor = to_qty)
 * @property bool $is_bidirectional Can convert in both directions
 * @property \Carbon\Carbon|null $effective_from Start date for conversion
 * @property \Carbon\Carbon|null $effective_to End date for conversion
 * @property string|null $notes Conversion description (e.g., "1 Box = 12 Packs of 6 Rolls")
 * @property bool $is_active Active status
 * @property bool $is_approved Organizational approval flag
 * @property int|null $created_by Backend user who created this
 * @property int|null $approved_by Backend user who approved this
 * @property \Carbon\Carbon|null $approved_at Approval timestamp
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
    public $table = 'omsb_organization_uom_conversions';

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
        'is_active',
        'is_approved'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'from_uom_id' => 'required|integer|exists:omsb_organization_unit_of_measures,id',
        'to_uom_id' => 'required|integer|exists:omsb_organization_unit_of_measures,id|different:from_uom_id',
        'conversion_factor' => 'required|numeric|min:0.000001',
        'is_bidirectional' => 'boolean',
        'effective_from' => 'nullable|date',
        'effective_to' => 'nullable|date|after_or_equal:effective_from',
        'is_active' => 'boolean',
        'is_approved' => 'boolean'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'from_uom_id.required' => 'Source UOM is required',
        'from_uom_id.exists' => 'Source UOM does not exist',
        'to_uom_id.required' => 'Target UOM is required',
        'to_uom_id.exists' => 'Target UOM does not exist',
        'to_uom_id.different' => 'Target UOM must be different from source UOM',
        'conversion_factor.required' => 'Conversion factor is required',
        'conversion_factor.min' => 'Conversion factor must be greater than zero',
        'effective_to.after_or_equal' => 'End date must be on or after start date'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'effective_from',
        'effective_to',
        'approved_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'conversion_factor' => 'float',
        'is_bidirectional' => 'boolean',
        'is_active' => 'boolean',
        'is_approved' => 'boolean'
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

        // Validate UOMs are compatible (same base)
        static::saving(function ($model) {
            $fromUom = UnitOfMeasure::find($model->from_uom_id);
            $toUom = UnitOfMeasure::find($model->to_uom_id);

            if ($fromUom && $toUom) {
                $fromBase = $fromUom->getUltimateBaseUom();
                $toBase = $toUom->getUltimateBaseUom();

                if ($fromBase->id !== $toBase->id) {
                    throw new \ValidationException([
                        'to_uom_id' => 'Cannot convert between incompatible UOM types (different base units)'
                    ]);
                }
            }
        });
    }

    /**
     * Get display name for this conversion
     */
    public function getDisplayNameAttribute(): string
    {
        if (!$this->from_uom || !$this->to_uom) {
            return 'Incomplete conversion';
        }
        
        return sprintf(
            '1 %s = %s %s',
            $this->from_uom->code,
            number_format($this->conversion_factor, 6),
            $this->to_uom->code
        );
    }

    /**
     * Check if conversion is currently effective
     */
    public function isEffective(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::now();

        if ($this->effective_from && $date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return true;
    }

    /**
     * Convert quantity using this conversion rule
     */
    public function convert(float $fromQuantity): float
    {
        return $fromQuantity * $this->conversion_factor;
    }

    /**
     * Convert quantity in reverse direction (if bidirectional)
     */
    public function convertReverse(float $toQuantity): ?float
    {
        if (!$this->is_bidirectional) {
            return null;
        }

        return $toQuantity / $this->conversion_factor;
    }

    /**
     * Scope: Active conversions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Approved conversions only
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: Currently effective conversions
     */
    public function scopeEffective($query, ?Carbon $date = null)
    {
        $date = $date ?? Carbon::now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', $date);
        })
        ->where(function ($q) use ($date) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>=', $date);
        });
    }

    /**
     * Scope: Find conversion between two UOMs
     */
    public function scopeBetween($query, int $fromUomId, int $toUomId)
    {
        return $query->where('from_uom_id', $fromUomId)
                     ->where('to_uom_id', $toUomId);
    }

    /**
     * Scope: Find bidirectional conversion between two UOMs
     */
    public function scopeBidirectionalBetween($query, int $uom1Id, int $uom2Id)
    {
        return $query->where(function ($q) use ($uom1Id, $uom2Id) {
            $q->where(function ($subQ) use ($uom1Id, $uom2Id) {
                $subQ->where('from_uom_id', $uom1Id)
                     ->where('to_uom_id', $uom2Id);
            })
            ->orWhere(function ($subQ) use ($uom1Id, $uom2Id) {
                $subQ->where('from_uom_id', $uom2Id)
                     ->where('to_uom_id', $uom1Id)
                     ->where('is_bidirectional', true);
            });
        });
    }
}
