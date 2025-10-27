<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;
use ValidationException;
use Omsb\Registrar\Traits\HasControlledDocumentNumber;

/**
 * Mrn Model - Material Received Note
 * 
 * Records goods receipt from suppliers into warehouses.
 * Creates from Goods Receipt Note in Procurement plugin.
 * Updates WarehouseItem quantities and creates InventoryLedger entries.
 *
 * @property int $id
 * @property string $mrn_number Unique document number
 * @property int $warehouse_id Receiving warehouse
 * @property int|null $goods_receipt_note_id Source GRN from Procurement
 * @property \Carbon\Carbon $received_date Date goods were received
 * @property string|null $delivery_note_number Vendor's delivery note
 * @property string|null $vehicle_number Delivery vehicle
 * @property string|null $driver_name Delivery driver
 * @property \Carbon\Carbon|null $received_time Time of receipt
 * @property string|null $remarks Additional notes
 * @property float $total_received_value Total value of goods received
 * @property string $status Document status (draft, submitted, approved, completed)
 * @property int $received_by Staff who received the goods
 * @property int|null $approved_by Staff who approved the MRN
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Mrn extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use HasControlledDocumentNumber;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mrns';

    /**
     * @var string document type code for registrar
     */
    protected string $documentTypeCode = 'MRN';

    /**
     * @var array statuses that lock the document
     */
    protected array $protectedStatuses = ['approved', 'completed'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        // TO DO: mrn_number is to be removed as it is ow redundant since we already have document_number
        'mrn_number',
        'document_number',
        'registry_id',
        'warehouse_id',
        'goods_receipt_note_id',
        'received_date',
        'delivery_note_number',
        'vehicle_number',
        'driver_name',
        'received_time',
        'remarks',
        'total_received_value',
        'status',
        'received_by',
        'approved_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'goods_receipt_note_id',
        'registry_id',
        'delivery_note_number',
        'vehicle_number',
        'driver_name',
        'received_time',
        'remarks',
        'approved_by',
        'previous_status',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'mrn_number' => 'required|max:255|unique:omsb_inventory_mrns,mrn_number',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'goods_receipt_note_id' => 'nullable|integer|exists:omsb_procurement_goods_receipt_notes,id',
        'received_date' => 'required|date',
        'delivery_note_number' => 'nullable|max:255',
        'vehicle_number' => 'nullable|max:50',
        'driver_name' => 'nullable|max:255',
        'received_time' => 'nullable|date',
        'total_received_value' => 'numeric|min:0',
        'status' => 'required|in:draft,submitted,approved,completed',
        'received_by' => 'required|integer|exists:omsb_organization_staff,id',
        'approved_by' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'mrn_number.required' => 'MRN number is required',
        'mrn_number.unique' => 'This MRN number is already in use',
        'warehouse_id.required' => 'Warehouse is required',
        'warehouse_id.exists' => 'Selected warehouse does not exist',
        'received_date.required' => 'Received date is required',
        'status.required' => 'Status is required',
        'status.in' => 'Invalid status',
        'received_by.required' => 'Received by staff is required',
        'received_by.exists' => 'Selected staff does not exist',
        'total_received_value.min' => 'Total value cannot be negative'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'received_date',
        'received_time',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'total_received_value' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse' => [
            Warehouse::class
        ],
        'goods_receipt_note' => [
            // TODO: Reference to Procurement plugin - GoodsReceiptNote model
            'Omsb\Procurement\Models\GoodsReceiptNote'
        ],
        'receiver' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'received_by'
        ],
        'approver' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'approved_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'items' => [
            MrnItem::class,
            'delete' => true
        ]
    ];

    /**
     * @var array morphMany relations for activity tracking
     */
    public $morphMany = [
        // TODO: Activity tracking via Feeder plugin
        // 'feeds' => [\Omsb\Feeder\Models\Feed::class, 'name' => 'feedable']
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
            
            // Default received_date to today if not set
            if (!$model->received_date) {
                $model->received_date = Carbon::today();
            }
        });

        // Prevent modification of non-draft documents
        static::updating(function ($model) {
            if ($model->getOriginal('status') !== 'draft' && $model->isDirty(['warehouse_id', 'received_by'])) {
                throw new ValidationException([
                    'status' => 'Cannot modify key fields of a non-draft MRN'
                ]);
            }
        });

        // Calculate total on save
        static::saving(function ($model) {
            if ($model->items) {
                $model->total_received_value = $model->items->sum('total_cost');
            }
        });

        // Prevent deletion of approved/completed documents
        static::deleting(function ($model) {
            if (in_array($model->status, ['approved', 'completed'])) {
                throw new ValidationException([
                    'status' => 'Cannot delete an approved or completed MRN'
                ]);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->mrn_number,
            $this->received_date->format('d M Y'),
            strtoupper($this->status)
        );
    }

    /**
     * Check if MRN can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if MRN can be approved
     */
    public function canApprove(): bool
    {
        return in_array($this->status, ['submitted', 'draft']);
    }

    /**
     * Check if MRN can be completed
     */
    public function canComplete(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if MRN is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if MRN is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if MRN is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if MRN is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Submit for approval
     */
    public function submit(): bool
    {
        if (!$this->isDraft()) {
            throw new ValidationException([
                'status' => 'Only draft MRNs can be submitted'
            ]);
        }

        if ($this->items->isEmpty()) {
            throw new ValidationException([
                'items' => 'Cannot submit an MRN without line items'
            ]);
        }

        $this->status = 'submitted';
        return $this->save();
    }

    /**
     * Approve the MRN
     */
    public function approve(int $approvedBy = null): bool
    {
        if (!$this->canApprove()) {
            throw new ValidationException([
                'status' => 'This MRN cannot be approved'
            ]);
        }

        if ($approvedBy) {
            $this->approved_by = $approvedBy;
        } elseif (BackendAuth::check()) {
            // Auto-set from current user
            $user = BackendAuth::getUser();
            // TODO: Map backend user to staff
            // $this->approved_by = $user->staff_id;
        }

        $this->status = 'approved';
        return $this->save();
    }

    /**
     * Complete the MRN (finalize and update inventory)
     * 
     * This creates InventoryLedger entries and updates WarehouseItem quantities
     */
    public function complete(): bool
    {
        if (!$this->canComplete()) {
            throw new ValidationException([
                'status' => 'Only approved MRNs can be completed'
            ]);
        }

        // TODO: Create InventoryLedger entries via InventoryLedgerService
        // foreach ($this->items as $item) {
        //     InventoryLedgerService::createReceiptEntry([...]);
        // }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Reject the MRN
     */
    public function reject(string $reason = null): bool
    {
        if ($this->isCompleted()) {
            throw new ValidationException([
                'status' => 'Cannot reject a completed MRN'
            ]);
        }

        if ($reason) {
            $this->remarks = $this->remarks 
                ? $this->remarks . "\n\nRejection reason: " . $reason 
                : "Rejection reason: " . $reason;
        }

        $this->status = 'draft';
        $this->approved_by = null;
        return $this->save();
    }

    /**
     * Calculate total value from items
     */
    public function calculateTotalValue(): float
    {
        return $this->items->sum('total_cost');
    }

    /**
     * Get item count
     */
    public function getItemCountAttribute(): int
    {
        return $this->items->count();
    }

    /**
     * Scope: Draft MRNs
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Submitted MRNs
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope: Approved MRNs
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Completed MRNs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: By warehouse
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope: By date range
     */
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('received_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Recent MRNs (within days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('received_date', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Get status options for dropdowns
     */
    public function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'completed' => 'Completed'
        ];
    }
}
