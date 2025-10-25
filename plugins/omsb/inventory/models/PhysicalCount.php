<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;
use ValidationException;

/**
 * PhysicalCount Model
 * 
 * Records physical inventory counting operations.
 * Compares system quantities with actual physical counts.
 * Generates StockAdjustment documents for variances.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class PhysicalCount extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_physical_counts';

    protected $fillable = [
        'count_number', 'warehouse_id', 'count_date', 'count_type',
        'cut_off_time', 'total_items_counted', 'variance_count',
        'notes', 'status', 'initiated_by', 'supervisor'
    ];

    protected $nullable = ['cut_off_time', 'notes', 'supervisor', 'created_by'];

    public $rules = [
        'count_number' => 'required|max:255|unique:omsb_inventory_physical_counts,count_number',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'count_date' => 'required|date',
        'count_type' => 'required|in:full,cycle,spot',
        'cut_off_time' => 'nullable|date',
        'total_items_counted' => 'integer|min:0',
        'variance_count' => 'integer|min:0',
        'status' => 'required|in:scheduled,in_progress,completed,variance_review',
        'initiated_by' => 'required|integer|exists:omsb_organization_staff,id',
        'supervisor' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    public $customMessages = [
        'count_number.required' => 'Count number is required',
        'count_number.unique' => 'This count number is already in use',
        'warehouse_id.required' => 'Warehouse is required',
        'count_date.required' => 'Count date is required',
        'count_type.required' => 'Count type is required',
        'status.required' => 'Status is required',
        'initiated_by.required' => 'Initiated by staff is required'
    ];

    protected $dates = ['count_date', 'cut_off_time', 'deleted_at'];
    protected $casts = ['total_items_counted' => 'integer', 'variance_count' => 'integer'];

    public $belongsTo = [
        'warehouse' => Warehouse::class,
        'initiator' => ['Omsb\Organization\Models\Staff', 'key' => 'initiated_by'],
        'supervisor_staff' => ['Omsb\Organization\Models\Staff', 'key' => 'supervisor'],
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by']
    ];

    public $hasMany = [
        'items' => [PhysicalCountItem::class, 'delete' => true]
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
            if (!$model->count_date) {
                $model->count_date = Carbon::today();
            }
            if (!$model->cut_off_time) {
                $model->cut_off_time = Carbon::now();
            }
        });

        static::saving(function ($model) {
            if ($model->items) {
                $model->total_items_counted = $model->items->count();
                $model->variance_count = $model->items->where('has_variance', true)->count();
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return sprintf('%s - %s (%s)', $this->count_number, $this->count_date->format('d M Y'), strtoupper($this->status));
    }

    public function canEdit(): bool { return in_array($this->status, ['scheduled', 'in_progress']); }
    public function canStart(): bool { return $this->status === 'scheduled'; }
    public function canComplete(): bool { return $this->status === 'in_progress'; }
    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }

    public function start(): bool
    {
        if (!$this->canStart()) {
            throw new ValidationException(['status' => 'Only scheduled counts can be started']);
        }
        $this->status = 'in_progress';
        return $this->save();
    }

    public function complete(): bool
    {
        if (!$this->canComplete()) {
            throw new ValidationException(['status' => 'Only in-progress counts can be completed']);
        }
        if ($this->items->isEmpty()) {
            throw new ValidationException(['items' => 'Cannot complete a count without items']);
        }
        $this->status = $this->variance_count > 0 ? 'variance_review' : 'completed';
        return $this->save();
    }

    public function scopeScheduled($query) { return $query->where('status', 'scheduled'); }
    public function scopeInProgress($query) { return $query->where('status', 'in_progress'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopeByWarehouse($query, int $warehouseId) { return $query->where('warehouse_id', $warehouseId); }
    public function scopeByType($query, string $type) { return $query->where('count_type', $type); }

    public function getStatusOptions(): array
    {
        return [
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'variance_review' => 'Variance Review'
        ];
    }

    public function getCountTypeOptions(): array
    {
        return [
            'full' => 'Full Count',
            'cycle' => 'Cycle Count',
            'spot' => 'Spot Check'
        ];
    }
}
