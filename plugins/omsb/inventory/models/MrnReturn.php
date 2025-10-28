<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;
use ValidationException;
use Omsb\Registrar\Traits\HasControlledDocumentNumber;

/**
 * MrnReturn Model - Material Received Note Return
 * 
 * Records returns of goods to vendors (damaged, incorrect, rejected items).
 * References original MRN and creates reverse inventory transactions.
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MrnReturn extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use HasControlledDocumentNumber;
    use \Omsb\Feeder\Traits\HasFeed;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mrn_returns';

    /**
     * @var string document type code for registrar
     */
    protected string $documentTypeCode = 'MRNR';

    /**
     * @var array statuses that lock the document
     */
    protected array $protectedStatuses = ['approved', 'completed'];

    /**
     * HasFeed trait configuration
     */
    protected $feedMessageTemplate = '{actor} {action} MRN Return {model_identifier}';
    protected $feedableActions = ['created', 'updated', 'deleted', 'submitted', 'approved', 'completed'];
    protected $feedSignificantFields = ['status', 'total_return_value', 'return_reason'];

    protected $fillable = [
        'return_number', 'document_number', 'registry_id', 'mrn_id', 'warehouse_id', 'return_date',
        'return_reason', 'notes', 'total_return_value', 'status',
        'returned_by', 'approved_by'
    ];

    protected $nullable = ['notes', 'approved_by', 'created_by', 'registry_id', 'previous_status'];

    public $rules = [
        'return_number' => 'required|max:255|unique:omsb_inventory_mrn_returns,return_number',
        'mrn_id' => 'required|integer|exists:omsb_inventory_mrns,id',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'return_date' => 'required|date',
        'return_reason' => 'required|in:damage,incorrect_item,quality_issue,excess_delivery,other',
        'total_return_value' => 'numeric|min:0',
        'status' => 'required|in:draft,submitted,approved,completed',
        'returned_by' => 'required|integer|exists:omsb_organization_staff,id',
        'approved_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    protected $dates = ['return_date', 'deleted_at'];
    protected $casts = ['total_return_value' => 'decimal:2'];

    public $belongsTo = [
        'mrn' => Mrn::class,
        'warehouse' => Warehouse::class,
        'returner' => ['Omsb\Organization\Models\Staff', 'key' => 'returned_by'],
        'approver' => ['Omsb\Organization\Models\Staff', 'key' => 'approved_by'],
        'creator' => [\Backend\Models\User::class, 'key' => 'created_by']
    ];

    public $hasMany = ['items' => [MrnReturnItem::class, 'delete' => true]];

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
    public function isDraft(): bool { return $this->status === 'draft'; }

    public function scopeDraft($query) { return $query->where('status', 'draft'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }

    public function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'completed' => 'Completed'
        ];
    }

    public function getReturnReasonOptions(): array
    {
        return [
            'damage' => 'Damage',
            'incorrect_item' => 'Incorrect Item',
            'quality_issue' => 'Quality Issue',
            'excess_delivery' => 'Excess Delivery',
            'other' => 'Other'
        ];
    }
}
