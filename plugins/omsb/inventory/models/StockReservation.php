<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;
use ValidationException;

/**
 * StockReservation Model
 * 
 * Manages stock allocations/reservations for future fulfillment.
 * Prevents double-allocation of inventory across different orders/requests.
 * Reservations reduce available quantity but not on-hand quantity.
 *
 * @property int $id
 * @property string $reservation_number Unique document number
 * @property int $warehouse_item_id SKU being reserved
 * @property float $reserved_quantity Quantity allocated
 * @property string $reservation_type Type of reservation (sales_order, work_order, transfer_request)
 * @property string $reference_document_type Polymorphic document type
 * @property int $reference_document_id Polymorphic document ID
 * @property \Carbon\Carbon $reserved_at Reservation timestamp
 * @property \Carbon\Carbon|null $expires_at Auto-release date
 * @property string|null $notes Additional notes
 * @property string $status Reservation status (active, fulfilled, expired, cancelled)
 * @property int $reserved_by Staff who made the reservation
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class StockReservation extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_stock_reservations';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'reservation_number',
        'warehouse_item_id',
        'reserved_quantity',
        'reservation_type',
        'reference_document_type',
        'reference_document_id',
        'reserved_at',
        'expires_at',
        'notes',
        'status',
        'reserved_by'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'expires_at',
        'notes',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'reservation_number' => 'required|max:255|unique:omsb_inventory_stock_reservations,reservation_number',
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'reserved_quantity' => 'required|numeric|min:0.000001',
        'reservation_type' => 'required|in:sales_order,work_order,transfer_request,manual',
        'reference_document_type' => 'required|max:255',
        'reference_document_id' => 'required|integer|min:1',
        'reserved_at' => 'required|date',
        'expires_at' => 'nullable|date|after:reserved_at',
        'status' => 'required|in:active,fulfilled,expired,cancelled',
        'reserved_by' => 'required|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'reservation_number.required' => 'Reservation number is required',
        'reservation_number.unique' => 'This reservation number is already in use',
        'warehouse_item_id.required' => 'Warehouse item is required',
        'warehouse_item_id.exists' => 'Selected warehouse item does not exist',
        'reserved_quantity.required' => 'Reserved quantity is required',
        'reserved_quantity.min' => 'Reserved quantity must be greater than zero',
        'reservation_type.required' => 'Reservation type is required',
        'reservation_type.in' => 'Invalid reservation type',
        'reference_document_type.required' => 'Reference document type is required',
        'reference_document_id.required' => 'Reference document ID is required',
        'reserved_at.required' => 'Reserved date is required',
        'expires_at.after' => 'Expiry date must be after reservation date',
        'status.required' => 'Status is required',
        'status.in' => 'Invalid status',
        'reserved_by.required' => 'Reserved by staff is required',
        'reserved_by.exists' => 'Selected staff does not exist'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'reserved_at',
        'expires_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'reserved_quantity' => 'decimal:6',
        'reference_document_id' => 'integer'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'staff' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'reserved_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    /**
     * @var array Polymorphic relations
     */
    public $morphTo = [
        'reference_document' => []
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
            
            // Default reserved_at to now if not set
            if (!$model->reserved_at) {
                $model->reserved_at = Carbon::now();
            }
        });

        // Validate available stock before creating
        static::creating(function ($model) {
            $model->validateAvailableStock();
        });

        // Prevent updates to fulfilled/cancelled reservations
        static::updating(function ($model) {
            if ($model->getOriginal('status') === 'fulfilled') {
                throw new ValidationException(['status' => 'Cannot modify a fulfilled reservation']);
            }
            if ($model->getOriginal('status') === 'cancelled' && $model->status !== 'active') {
                throw new ValidationException(['status' => 'Cannot modify a cancelled reservation']);
            }
        });

        // Update warehouse item reserved quantity on create
        static::created(function ($model) {
            if ($model->status === 'active') {
                $model->warehouse_item->adjustReservedQuantity($model->reserved_quantity);
            }
        });

        // Handle status changes and adjust reserved quantity
        static::updated(function ($model) {
            $oldStatus = $model->getOriginal('status');
            $newStatus = $model->status;
            
            // If becoming active, add to reserved
            if ($oldStatus !== 'active' && $newStatus === 'active') {
                $model->warehouse_item->adjustReservedQuantity($model->reserved_quantity);
            }
            
            // If leaving active, remove from reserved
            if ($oldStatus === 'active' && $newStatus !== 'active') {
                $model->warehouse_item->adjustReservedQuantity(-$model->reserved_quantity);
            }
            
            // If quantity changed while active, adjust difference
            if ($oldStatus === 'active' && $newStatus === 'active') {
                $oldQty = $model->getOriginal('reserved_quantity');
                $newQty = $model->reserved_quantity;
                if ($oldQty !== $newQty) {
                    $model->warehouse_item->adjustReservedQuantity($newQty - $oldQty);
                }
            }
        });

        // Release reserved quantity on delete if active
        static::deleting(function ($model) {
            if ($model->status === 'active') {
                $model->warehouse_item->adjustReservedQuantity(-$model->reserved_quantity);
            }
        });
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf(
            '%s (%s)',
            $this->reservation_number,
            $this->reservation_type
        );
    }

    /**
     * Check if reservation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if reservation is fulfilled
     */
    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    /**
     * Check if reservation is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if reservation is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Fulfill the reservation
     */
    public function fulfill(): bool
    {
        if (!$this->isActive()) {
            throw new ValidationException([
                'status' => 'Only active reservations can be fulfilled'
            ]);
        }

        $this->status = 'fulfilled';
        return $this->save();
    }

    /**
     * Cancel the reservation
     */
    public function cancel(string $reason = null): bool
    {
        if ($this->isFulfilled()) {
            throw new ValidationException([
                'status' => 'Cannot cancel a fulfilled reservation'
            ]);
        }

        if ($reason) {
            $this->notes = $this->notes ? $this->notes . "\n\nCancellation reason: " . $reason : "Cancellation reason: " . $reason;
        }

        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Extend expiry date
     */
    public function extendExpiry(Carbon $newExpiryDate): bool
    {
        if (!$this->isActive()) {
            throw new ValidationException([
                'status' => 'Only active reservations can be extended'
            ]);
        }

        if ($newExpiryDate->isPast()) {
            throw new ValidationException([
                'expires_at' => 'New expiry date must be in the future'
            ]);
        }

        $this->expires_at = $newExpiryDate;
        return $this->save();
    }

    /**
     * Mark as expired (for automated processes)
     */
    public function markExpired(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $this->status = 'expired';
        return $this->save();
    }

    /**
     * Validate available stock before reservation
     */
    protected function validateAvailableStock(): void
    {
        $warehouseItem = $this->warehouse_item;
        
        if (!$warehouseItem) {
            throw new ValidationException([
                'warehouse_item_id' => 'Warehouse item not found'
            ]);
        }

        $availableQty = $warehouseItem->quantity_on_hand - $warehouseItem->quantity_reserved;
        
        if ($this->reserved_quantity > $availableQty) {
            throw new ValidationException([
                'reserved_quantity' => sprintf(
                    'Cannot reserve %s units. Only %s units available.',
                    $this->reserved_quantity,
                    $availableQty
                )
            ]);
        }
    }

    /**
     * Scope: Active reservations
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Fulfilled reservations
     */
    public function scopeFulfilled($query)
    {
        return $query->where('status', 'fulfilled');
    }

    /**
     * Scope: Expired reservations
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope: Cancelled reservations
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Expiring soon (within days)
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now()->addDays($days));
    }

    /**
     * Scope: By reservation type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('reservation_type', $type);
    }

    /**
     * Scope: By warehouse item
     */
    public function scopeByWarehouseItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }

    /**
     * Scope: By reference document
     */
    public function scopeByReferenceDocument($query, string $type, int $id)
    {
        return $query->where('reference_document_type', $type)
            ->where('reference_document_id', $id);
    }

    /**
     * Get status options for dropdowns
     */
    public function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'fulfilled' => 'Fulfilled',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled'
        ];
    }

    /**
     * Get reservation type options for dropdowns
     */
    public function getReservationTypeOptions(): array
    {
        return [
            'sales_order' => 'Sales Order',
            'work_order' => 'Work Order',
            'transfer_request' => 'Transfer Request',
            'manual' => 'Manual Reservation'
        ];
    }
}
