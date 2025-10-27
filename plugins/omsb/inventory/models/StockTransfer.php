<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Omsb\Registrar\Traits\HasControlledDocumentNumber;
use Carbon\Carbon;
use ValidationException;

/**
 * StockTransfer Model
 * 
 * Records inter-warehouse stock transfers.
 * Manages movement of inventory from one warehouse to another.
 * Creates paired InventoryLedger entries (decrease at source, increase at destination).
 *
 * @property int $id
 * @property string $transfer_number Unique document number
 * @property int $from_warehouse_id Source warehouse
 * @property int $to_warehouse_id Destination warehouse
 * @property int $requested_by Staff who requested the transfer
 * @property int|null $approved_by Staff who approved the transfer
 * @property int|null $shipped_by Staff who shipped the goods
 * @property int|null $received_by Staff who received the goods
 * @property \Carbon\Carbon $transfer_date Transfer document date
 * @property \Carbon\Carbon|null $requested_date When initially requested
 * @property \Carbon\Carbon|null $shipped_date When goods were shipped
 * @property \Carbon\Carbon|null $received_date When goods were received
 * @property string|null $transportation_method Method of transport (truck, courier, internal)
 * @property string|null $tracking_number External carrier tracking number
 * @property string|null $notes Additional notes
 * @property float $total_transfer_value Total value of goods transferred
 * @property string $status Document status (draft, approved, in_transit, received, cancelled)
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class StockTransfer extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use HasControlledDocumentNumber;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_stock_transfers';

    /**
     * @var string document type code for registrar
     */
    protected string $documentTypeCode = 'STFR';

    /**
     * @var array statuses that lock the document
     */
    protected array $protectedStatuses = ['in_transit', 'received'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'transfer_number',
        'document_number',
        'registry_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'requested_by',
        'approved_by',
        'shipped_by',
        'received_by',
        'transfer_date',
        'requested_date',
        'shipped_date',
        'received_date',
        'transportation_method',
        'tracking_number',
        'notes',
        'total_transfer_value',
        'status'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'approved_by',
        'shipped_by',
        'received_by',
        'requested_date',
        'shipped_date',
        'received_date',
        'transportation_method',
        'tracking_number',
        'notes',
        'created_by',
        'registry_id',
        'previous_status'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'transfer_number' => 'required|max:255|unique:omsb_inventory_stock_transfers,transfer_number',
        'from_warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id|different:to_warehouse_id',
        'to_warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id|different:from_warehouse_id',
        'requested_by' => 'required|integer|exists:omsb_organization_staff,id',
        'approved_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'shipped_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'received_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'transfer_date' => 'required|date',
        'requested_date' => 'nullable|date|before_or_equal:transfer_date',
        'shipped_date' => 'nullable|date',
        'received_date' => 'nullable|date',
        'transportation_method' => 'nullable|in:truck,courier,internal,van,air,sea',
        'total_transfer_value' => 'numeric|min:0',
        'status' => 'required|in:draft,approved,in_transit,received,cancelled'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'transfer_number.required' => 'Transfer number is required',
        'transfer_number.unique' => 'This transfer number is already in use',
        'from_warehouse_id.required' => 'Source warehouse is required',
        'from_warehouse_id.different' => 'Source and destination warehouses must be different',
        'to_warehouse_id.required' => 'Destination warehouse is required',
        'to_warehouse_id.different' => 'Source and destination warehouses must be different',
        'requested_by.required' => 'Requested by staff is required',
        'transfer_date.required' => 'Transfer date is required',
        'status.required' => 'Status is required',
        'status.in' => 'Invalid status'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'transfer_date',
        'requested_date',
        'shipped_date',
        'received_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'total_transfer_value' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'from_warehouse' => [
            Warehouse::class,
            'key' => 'from_warehouse_id'
        ],
        'to_warehouse' => [
            Warehouse::class,
            'key' => 'to_warehouse_id'
        ],
        'requester' => [
            'Omsb\Organization\Models\Staff',
            'key' => 'requested_by'
        ],
        'approver' => [
            'Omsb\Organization\Models\Staff',
            'key' => 'approved_by'
        ],
        'shipper' => [
            'Omsb\Organization\Models\Staff',
            'key' => 'shipped_by'
        ],
        'receiver' => [
            'Omsb\Organization\Models\Staff',
            'key' => 'received_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'items' => [
            StockTransferItem::class,
            'delete' => true
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (BackendAuth::check()) {
                $model->created_by = BackendAuth::getUser()->id;
            }
            if (!$model->transfer_date) {
                $model->transfer_date = Carbon::today();
            }
        });

        static::updating(function ($model) {
            if ($model->getOriginal('status') !== 'draft' && $model->isDirty(['from_warehouse_id', 'to_warehouse_id'])) {
                throw new ValidationException([
                    'status' => 'Cannot modify warehouses of a non-draft transfer'
                ]);
            }
        });

        static::saving(function ($model) {
            if ($model->items) {
                $model->total_transfer_value = $model->items->sum('total_cost');
            }
        });

        static::deleting(function ($model) {
            if (in_array($model->status, ['in_transit', 'received'])) {
                throw new ValidationException([
                    'status' => 'Cannot delete a transfer that is in transit or received'
                ]);
            }
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return sprintf('%s - %s (%s)', $this->transfer_number, $this->transfer_date->format('d M Y'), strtoupper($this->status));
    }

    public function canEdit(): bool { return $this->status === 'draft'; }
    public function canApprove(): bool { return in_array($this->status, ['draft']); }
    public function canShip(): bool { return $this->status === 'approved'; }
    public function canReceive(): bool { return $this->status === 'in_transit'; }
    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isInTransit(): bool { return $this->status === 'in_transit'; }
    public function isReceived(): bool { return $this->status === 'received'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function approve(int $approvedBy = null): bool
    {
        if (!$this->canApprove()) {
            throw new ValidationException(['status' => 'This transfer cannot be approved']);
        }
        if ($approvedBy) {
            $this->approved_by = $approvedBy;
        }
        $this->status = 'approved';
        return $this->save();
    }

    public function ship(int $shippedBy = null): bool
    {
        if (!$this->canShip()) {
            throw new ValidationException(['status' => 'Only approved transfers can be shipped']);
        }
        if ($shippedBy) {
            $this->shipped_by = $shippedBy;
        }
        $this->shipped_date = Carbon::now();
        $this->status = 'in_transit';
        return $this->save();
    }

    public function receive(int $receivedBy = null): bool
    {
        if (!$this->canReceive()) {
            throw new ValidationException(['status' => 'Only in-transit transfers can be received']);
        }
        if ($receivedBy) {
            $this->received_by = $receivedBy;
        }
        $this->received_date = Carbon::now();
        $this->status = 'received';
        return $this->save();
    }

    public function cancel(string $reason = null): bool
    {
        if ($this->isReceived()) {
            throw new ValidationException(['status' => 'Cannot cancel a received transfer']);
        }
        if ($reason) {
            $this->notes = $this->notes ? $this->notes . "\n\nCancellation reason: " . $reason : "Cancellation reason: " . $reason;
        }
        $this->status = 'cancelled';
        return $this->save();
    }

    public function getItemCountAttribute(): int { return $this->items->count(); }

    public function scopeDraft($query) { return $query->where('status', 'draft'); }
    public function scopeApproved($query) { return $query->where('status', 'approved'); }
    public function scopeInTransit($query) { return $query->where('status', 'in_transit'); }
    public function scopeReceived($query) { return $query->where('status', 'received'); }
    public function scopeCancelled($query) { return $query->where('status', 'cancelled'); }
    public function scopeByFromWarehouse($query, int $warehouseId) { return $query->where('from_warehouse_id', $warehouseId); }
    public function scopeByToWarehouse($query, int $warehouseId) { return $query->where('to_warehouse_id', $warehouseId); }
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate) { return $query->whereBetween('transfer_date', [$startDate, $endDate]); }

    public function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'in_transit' => 'In Transit',
            'received' => 'Received',
            'cancelled' => 'Cancelled'
        ];
    }

    public function getTransportationMethodOptions(): array
    {
        return [
            'truck' => 'Truck',
            'courier' => 'Courier',
            'internal' => 'Internal',
            'van' => 'Van',
            'air' => 'Air',
            'sea' => 'Sea'
        ];
    }
}
