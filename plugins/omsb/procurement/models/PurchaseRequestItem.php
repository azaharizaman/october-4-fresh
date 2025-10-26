<?php namespace Omsb\Procurement\Models;

use Model;

/**
 * PurchaseRequestItem Model
 * 
 * Line items for purchase requests
 *
 * @property int $id
 * @property int $line_number Line number in document
 * @property string $item_description Item description
 * @property string $unit_of_measure Unit of measure
 * @property float $quantity_requested Requested quantity
 * @property float|null $estimated_unit_cost Estimated unit cost
 * @property float|null $estimated_total_cost Estimated total cost
 * @property string|null $notes Line item notes
 * @property int $purchase_request_id Parent purchase request
 * @property int|null $purchaseable_item_id Referenced purchaseable item
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class PurchaseRequestItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_purchase_request_items';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'line_number',
        'item_description',
        'unit_of_measure',
        'quantity_requested',
        'estimated_unit_cost',
        'estimated_total_cost',
        'notes',
        'purchase_request_id',
        'purchaseable_item_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'estimated_unit_cost',
        'estimated_total_cost',
        'notes',
        'purchaseable_item_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'line_number' => 'required|integer|min:1',
        'item_description' => 'required',
        'unit_of_measure' => 'required|max:255',
        'quantity_requested' => 'required|numeric|min:0.01',
        'estimated_unit_cost' => 'nullable|numeric|min:0',
        'estimated_total_cost' => 'nullable|numeric|min:0',
        'purchase_request_id' => 'required|integer|exists:omsb_procurement_purchase_requests,id',
        'purchaseable_item_id' => 'nullable|integer|exists:omsb_procurement_purchaseable_items,id'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'line_number' => 'integer',
        'quantity_requested' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'purchase_request' => [
            PurchaseRequest::class
        ],
        'purchaseable_item' => [
            PurchaseableItem::class
        ]
    ];

    public $hasMany = [
        'vendor_quotation_items' => [
            VendorQuotationItem::class,
            'key' => 'purchase_request_item_id'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Auto-calculate estimated_total_cost
        static::saving(function ($model) {
            if ($model->quantity_requested && $model->estimated_unit_cost) {
                $model->estimated_total_cost = $model->quantity_requested * $model->estimated_unit_cost;
            }
            
            // Auto-populate from purchaseable_item if selected
            if ($model->purchaseable_item_id) {
                // Ensure relationship is loaded to avoid extra query per property access
                if (!$model->relationLoaded('purchaseable_item')) {
                    $model->load('purchaseable_item');
                }
                if ($model->purchaseable_item) {
                    if (!$model->item_description) {
                        $model->item_description = $model->purchaseable_item->name;
                    }
                    if (!$model->unit_of_measure) {
                        $model->unit_of_measure = $model->purchaseable_item->unit_of_measure;
                    }
                    if (!$model->estimated_unit_cost && $model->purchaseable_item->standard_cost) {
                        $model->estimated_unit_cost = $model->purchaseable_item->standard_cost;
                    }
                }
            }
        });

        // Recalculate parent PR total after save/delete
        static::saved(function ($model) {
            if ($model->purchase_request) {
                $model->purchase_request->recalculateTotal();
            }
        });

        static::deleted(function ($model) {
            if ($model->purchase_request) {
                $model->purchase_request->recalculateTotal();
            }
        });
    }

    /**
     * Get display description
     */
    public function getDisplayDescriptionAttribute(): string
    {
        $desc = $this->line_number . '. ' . $this->item_description;
        $desc .= ' (' . $this->quantity_requested . ' ' . $this->unit_of_measure . ')';
        
        return $desc;
    }

    /**
     * Get purchaseable item options for dropdown
     */
    public function getPurchaseableItemIdOptions(): array
    {
        return PurchaseableItem::active()
            ->orderBy('code')
            ->pluck('full_display', 'id')
            ->toArray();
    }
}
