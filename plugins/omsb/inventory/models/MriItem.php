<?php namespace Omsb\Inventory\Models;

use Model;
use ValidationException;

/**
 * MriItem Model - Material Request Issuance Line Item
 * 
 * Individual line items in a Material Request Issuance.
 * Tracks requested, approved, and issued quantities.
 * Supports multi-UOM tracking with conversion factors.
 * Handles lot/batch and serial number tracking.
 *
 * @property int $id
 * @property int $mri_id Parent MRI document
 * @property int $warehouse_item_id SKU being issued
 * @property float $requested_quantity Quantity requested
 * @property float $approved_quantity Quantity approved for issue
 * @property float $issued_quantity Quantity actually issued
 * @property float $unit_cost Cost per unit (for valuation)
 * @property float $total_cost Issued quantity × unit cost
 * @property int $issue_uom_id UOM used for issue
 * @property float $issued_quantity_in_uom Quantity in issue UOM
 * @property float $issued_quantity_in_default_uom Converted to default UOM
 * @property float $conversion_factor_used Conversion audit trail
 * @property string|null $lot_number Lot/batch number if applicable
 * @property array|null $serial_numbers Array of serial numbers if applicable
 * @property string|null $remarks Additional notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MriItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mri_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'mri_id',
        'warehouse_item_id',
        'requested_quantity',
        'approved_quantity',
        'issued_quantity',
        'unit_cost',
        'total_cost',
        'issue_uom_id',
        'issued_quantity_in_uom',
        'issued_quantity_in_default_uom',
        'conversion_factor_used',
        'lot_number',
        'serial_numbers',
        'remarks'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'lot_number',
        'serial_numbers',
        'remarks'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'mri_id' => 'required|integer|exists:omsb_inventory_mris,id',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'requested_quantity' => 'required|numeric|min:0',
        'approved_quantity' => 'required|numeric|min:0',
        'issued_quantity' => 'required|numeric|min:0',
        'unit_cost' => 'required|numeric|min:0',
        'total_cost' => 'required|numeric|min:0',
        'issue_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'issued_quantity_in_uom' => 'required|numeric|min:0',
        'issued_quantity_in_default_uom' => 'required|numeric|min:0',
        'conversion_factor_used' => 'required|numeric|min:0.000001',
        'lot_number' => 'nullable|max:255',
        'serial_numbers' => 'nullable|json'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'mri_id.required' => 'MRI is required',
        'mri_id.exists' => 'Selected MRI does not exist',
        'warehouse_item_id.required' => 'Warehouse item is required',
        'warehouse_item_id.exists' => 'Selected warehouse item does not exist',
        'requested_quantity.required' => 'Requested quantity is required',
        'requested_quantity.min' => 'Requested quantity cannot be negative',
        'approved_quantity.required' => 'Approved quantity is required',
        'approved_quantity.min' => 'Approved quantity cannot be negative',
        'issued_quantity.required' => 'Issued quantity is required',
        'issued_quantity.min' => 'Issued quantity cannot be negative',
        'unit_cost.required' => 'Unit cost is required',
        'unit_cost.min' => 'Unit cost cannot be negative',
        'total_cost.required' => 'Total cost is required',
        'total_cost.min' => 'Total cost cannot be negative',
        'issue_uom_id.required' => 'Issue UOM is required',
        'issue_uom_id.exists' => 'Selected UOM does not exist',
        'serial_numbers.json' => 'Serial numbers must be valid JSON'
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
        'requested_quantity' => 'decimal:6',
        'approved_quantity' => 'decimal:6',
        'issued_quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'issued_quantity_in_uom' => 'decimal:6',
        'issued_quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6',
        'serial_numbers' => 'json'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'mri' => [
            Mri::class
        ],
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'issue_uom' => [
            UnitOfMeasure::class,
            'key' => 'issue_uom_id'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-calculate total cost before saving
        static::saving(function ($model) {
            // Calculate total cost
            $model->total_cost = $model->issued_quantity * $model->unit_cost;
            
            // Validate approved >= issued
            if ($model->approved_quantity < $model->issued_quantity) {
                throw new ValidationException([
                    'issued_quantity' => 'Issued quantity cannot exceed approved quantity'
                ]);
            }
            
            // Validate requested >= approved
            if ($model->requested_quantity < $model->approved_quantity) {
                throw new ValidationException([
                    'approved_quantity' => 'Approved quantity cannot exceed requested quantity'
                ]);
            }
            
            // If conversion factor not set, calculate it
            if (!$model->conversion_factor_used && $model->issued_quantity_in_uom > 0) {
                $model->conversion_factor_used = $model->issued_quantity_in_default_uom / $model->issued_quantity_in_uom;
            }
        });

        // Validate stock availability before creating/updating
        static::saving(function ($model) {
            if ($model->issued_quantity > 0) {
                $warehouseItem = $model->warehouse_item;
                if ($warehouseItem && $model->issued_quantity > $warehouseItem->quantity_on_hand) {
                    throw new ValidationException([
                        'issued_quantity' => sprintf(
                            'Cannot issue %s units. Only %s units available in stock.',
                            $model->issued_quantity,
                            $warehouseItem->quantity_on_hand
                        )
                    ]);
                }
            }
        });

        // Validate serial numbers if required
        static::saving(function ($model) {
            if ($model->warehouse_item && $model->warehouse_item->serial_tracking_enabled) {
                if ($model->issued_quantity > 0 && empty($model->serial_numbers)) {
                    throw new ValidationException([
                        'serial_numbers' => 'Serial numbers are required for this item'
                    ]);
                }
                
                // Validate serial number count matches quantity (for whole units)
                if (!empty($model->serial_numbers) && is_array($model->serial_numbers)) {
                    $serialCount = count($model->serial_numbers);
                    if ($serialCount != (int)$model->issued_quantity) {
                        throw new ValidationException([
                            'serial_numbers' => sprintf(
                                'Serial number count (%d) must match issued quantity (%d)',
                                $serialCount,
                                (int)$model->issued_quantity
                            )
                        ]);
                    }
                }
            }
        });

        // Update lot/batch quantities on issue
        static::created(function ($model) {
            if ($model->lot_number && $model->warehouse_item->lot_tracking_enabled) {
                // TODO: Update LotBatch issued quantity
                // LotBatch::issueFromMri($model);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $itemName = $this->warehouse_item ? $this->warehouse_item->display_name : 'Unknown Item';
        return sprintf(
            '%s - Qty: %s',
            $itemName,
            $this->issued_quantity
        );
    }

    /**
     * Calculate total cost (can be called manually)
     */
    public function calculateTotalCost(): float
    {
        return $this->issued_quantity * $this->unit_cost;
    }

    /**
     * Get variance between requested and issued
     */
    public function getVarianceAttribute(): float
    {
        return $this->issued_quantity - $this->requested_quantity;
    }

    /**
     * Get variance percentage
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->requested_quantity === 0) {
            return 0;
        }
        return ($this->variance / $this->requested_quantity) * 100;
    }

    /**
     * Check if full quantity was issued
     */
    public function isFullyIssued(): bool
    {
        return $this->issued_quantity >= $this->approved_quantity;
    }

    /**
     * Check if partially issued
     */
    public function isPartiallyIssued(): bool
    {
        return $this->issued_quantity > 0 && $this->issued_quantity < $this->approved_quantity;
    }

    /**
     * Check if over-issued
     */
    public function isOverIssued(): bool
    {
        return $this->issued_quantity > $this->approved_quantity;
    }

    /**
     * Get fulfillment percentage
     */
    public function getFulfillmentPercentageAttribute(): float
    {
        if ($this->approved_quantity == 0) {
            return 0;
        }
        return ($this->issued_quantity / $this->approved_quantity) * 100;
    }

    /**
     * Convert quantity from issue UOM to default UOM
     */
    public function convertToDefaultUom(float $quantityInIssueUom): float
    {
        return $quantityInIssueUom * $this->conversion_factor_used;
    }

    /**
     * Convert quantity from default UOM to issue UOM
     */
    public function convertFromDefaultUom(float $quantityInDefaultUom): float
    {
        if ($this->conversion_factor_used === 0) {
            return 0;
        }
        return $quantityInDefaultUom / $this->conversion_factor_used;
    }

    /**
     * Get pending quantity (approved but not yet issued)
     */
    public function getPendingQuantityAttribute(): float
    {
        return max(0, $this->approved_quantity - $this->issued_quantity);
    }

    /**
     * Scope: Fully issued items
     */
    public function scopeFullyIssued($query)
    {
        return $query->whereRaw('issued_quantity >= approved_quantity');
    }

    /**
     * Scope: Partially issued items
     */
    public function scopePartiallyIssued($query)
    {
        return $query->where('issued_quantity', '>', 0)
            ->whereRaw('issued_quantity < approved_quantity');
    }

    /**
     * Scope: Pending items (approved but not issued)
     */
    public function scopePending($query)
    {
        return $query->whereRaw('issued_quantity < approved_quantity');
    }

    /**
     * Scope: Items with lot tracking
     */
    public function scopeWithLotTracking($query)
    {
        return $query->whereNotNull('lot_number');
    }

    /**
     * Scope: Items with serial tracking
     */
    public function scopeWithSerialTracking($query)
    {
        return $query->whereNotNull('serial_numbers');
    }

    /**
     * Scope: By MRI
     */
    public function scopeByMri($query, int $mriId)
    {
        return $query->where('mri_id', $mriId);
    }

    /**
     * Scope: By warehouse item
     */
    public function scopeByWarehouseItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }
}
