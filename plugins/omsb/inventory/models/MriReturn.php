<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * MriReturn Model - Material Request Issuance Return
 * 
 * Records returns of issued materials from requesters (unused, excess items).
 * References original MRI and creates reverse inventory transactions.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MriReturn extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'omsb_inventory_mri_returns';

    protected $fillable = [
        'return_number', 'mri_id', 'warehouse_id', 'return_date',
        'return_reason', 'notes', 'total_return_value', 'status',
        'returned_by', 'received_by'
    ];

    protected $nullable = ['notes', 'received_by', 'created_by'];

    public $rules = [
        'return_number' => 'required|max:255|unique:omsb_inventory_mri_returns,return_number',
        'mri_id' => 'required|integer|exists:omsb_inventory_mris,id',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'return_date' => 'required|date',
        'return_reason' => 'required|in:unused,excess,damaged,incorrect,other',
        'total_return_value' => 'numeric|min:0',
        'status' => 'required|in:draft,submitted,approved,completed',
        'returned_by' => 'required|integer|exists:omsb_organization_staff,id',
        'received_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    protected $dates = ['return_date', 'deleted_at'];
    protected $casts = ['total_return_value' => 'decimal:2'];

    public $belongsTo = [
        'mri' => Mri::class,
        'warehouse' => Warehouse::class,
        'returner' => ['Omsb\Organization\Models\Staff', 'key' => 'returned_by'],
        'receiver' => ['Omsb\Organization\Models\Staff', 'key' => 'received_by'],
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by']
    ];

    public $hasMany = ['items' => [MriReturnItem::class, 'delete' => true]];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
            if (!$model->return_date) {
                $model->return_date = Carbon::today();
            }
        });
        static::saving(function ($model) {
            if ($model->items) {
                $model->total_return_value = $model->items->sum('total_cost');
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return sprintf('%s - %s', $this->return_number, $this->return_date->format('d M Y'));
    }

    public function canEdit(): bool { return $this->status === 'draft'; }
    public function scopeDraft($query) { return $query->where('status', 'draft'); }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'completed' => 'Completed'];
    }

    public function getReturnReasonOptions(): array
    {
        return ['unused' => 'Unused', 'excess' => 'Excess', 'damaged' => 'Damaged', 'incorrect' => 'Incorrect', 'other' => 'Other'];
    }
}
