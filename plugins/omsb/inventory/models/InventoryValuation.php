<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * InventoryValuation Model
 * 
 * Period-end inventory valuation reports.
 * Calculates inventory value using configured costing method (FIFO/LIFO/Average).
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class InventoryValuation extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Omsb\Feeder\Traits\HasFeed;

    public $table = 'omsb_inventory_inventory_valuations';

    /**
     * HasFeed trait configuration
     */
    protected $feedMessageTemplate = '{actor} {action} Inventory Valuation {model_identifier}';
    protected $feedableActions = ['created', 'updated', 'deleted', 'initiated', 'completed'];
    protected $feedSignificantFields = ['status', 'total_valuation_amount', 'valuation_method', 'valuation_date'];

    protected $fillable = [
        'valuation_number', 'inventory_period_id', 'warehouse_id',
        'valuation_date', 'valuation_method', 'total_valuation_amount',
        'total_items', 'notes', 'status'
    ];

    protected $nullable = ['notes', 'created_by'];

    public $rules = [
        'valuation_number' => 'required|max:255|unique:omsb_inventory_inventory_valuations,valuation_number',
        'inventory_period_id' => 'required|integer|exists:omsb_inventory_inventory_periods,id',
        'warehouse_id' => 'nullable|integer|exists:omsb_inventory_warehouses,id',
        'valuation_date' => 'required|date',
        'valuation_method' => 'required|in:FIFO,LIFO,Average',
        'total_valuation_amount' => 'numeric|min:0',
        'total_items' => 'integer|min:0',
        'status' => 'required|in:draft,in_progress,completed'
    ];

    protected $dates = ['valuation_date', 'deleted_at'];
    protected $casts = [
        'total_valuation_amount' => 'decimal:2',
        'total_items' => 'integer'
    ];

    public $belongsTo = [
        'inventory_period' => InventoryPeriod::class,
        'warehouse' => Warehouse::class,
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by']
    ];

    public $hasMany = ['items' => [InventoryValuationItem::class, 'delete' => true]];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
            if (!$model->valuation_date) {
                $model->valuation_date = Carbon::today();
            }
        });
        static::saving(function ($model) {
            if ($model->items) {
                $model->total_valuation_amount = $model->items->sum('valuation_amount');
                $model->total_items = $model->items->count();
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return sprintf('%s - %s', $this->valuation_number, $this->valuation_date->format('d M Y'));
    }

    public function canEdit(): bool { return in_array($this->status, ['draft', 'in_progress']); }
    public function scopeDraft($query) { return $query->where('status', 'draft'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Draft', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
    }

    public function getValuationMethodOptions(): array
    {
        return ['FIFO' => 'FIFO (First In, First Out)', 'LIFO' => 'LIFO (Last In, First Out)', 'Average' => 'Average Cost'];
    }
}
