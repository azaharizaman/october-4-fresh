<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * SerialNumber Model
 * 
 * Tracks individual items by serial number.
 * Essential for high-value items, equipment, and assets requiring individual tracking.
 *
 * @property int $id
 * @property int $warehouse_item_id Parent warehouse item
 * @property string $serial_number Unique serial identifier
 * @property string $status Status (available, reserved, issued, damaged)
 * @property \Carbon\Carbon|null $received_date When item was received
 * @property \Carbon\Carbon|null $issued_date When item was issued
 * @property string|null $manufacturer_serial Original manufacturer serial
 * @property string|null $notes Additional information
 * @property int|null $last_transaction_id Last ledger transaction
 * @property int|null $current_holder_id Staff currently holding the item
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class SerialNumber extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_serial_numbers';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'warehouse_item_id',
        'serial_number',
        'status',
        'received_date',
        'issued_date',
        'manufacturer_serial',
        'notes',
        'last_transaction_id',
        'current_holder_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'received_date',
        'issued_date',
        'manufacturer_serial',
        'notes',
        'last_transaction_id',
        'current_holder_id',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'serial_number' => 'required|max:255|unique:omsb_inventory_serial_numbers,serial_number',
        'status' => 'required|in:available,reserved,issued,damaged',
        'received_date' => 'nullable|date',
        'issued_date' => 'nullable|date|after_or_equal:received_date',
        'last_transaction_id' => 'nullable|integer|exists:omsb_inventory_inventory_ledgers,id',
        'current_holder_id' => 'nullable|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'warehouse_item_id.required' => 'Warehouse item is required',
        'serial_number.required' => 'Serial number is required',
        'serial_number.unique' => 'This serial number is already in use',
        'status.required' => 'Status is required',
        'issued_date.after_or_equal' => 'Issue date must be on or after received date'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'received_date',
        'issued_date',
        'deleted_at'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'last_transaction' => [
            InventoryLedger::class,
            'key' => 'last_transaction_id'
        ],
        'current_holder' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'current_holder_id'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
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

            // Auto-set received date if not provided
            if (!$model->received_date) {
                $model->received_date = Carbon::now();
            }
        });
    }

    /**
     * Get display name for the serial number
     */
    public function getDisplayNameAttribute(): string
    {
        $display = $this->serial_number;
        
        if ($this->status !== 'available') {
            $display .= ' (' . ucfirst($this->status) . ')';
        }
        
        return $display;
    }

    /**
     * Get full display with warehouse item info
     */
    public function getFullDisplayAttribute(): string
    {
        $display = $this->display_name;
        
        if ($this->warehouse_item) {
            $display .= ' - ' . $this->warehouse_item->display_name;
        }
        
        return $display;
    }

    /**
     * Scope: Available serial numbers only
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope: Reserved serial numbers
     */
    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    /**
     * Scope: Issued serial numbers
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope: Damaged serial numbers
     */
    public function scopeDamaged($query)
    {
        return $query->where('status', 'damaged');
    }

    /**
     * Scope: Filter by warehouse item
     */
    public function scopeForItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }

    /**
     * Scope: Filter by current holder
     */
    public function scopeHeldBy($query, int $staffId)
    {
        return $query->where('current_holder_id', $staffId);
    }

    /**
     * Check if serial number is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if serial number is reserved
     */
    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    /**
     * Check if serial number is issued
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Check if serial number is damaged
     */
    public function isDamaged(): bool
    {
        return $this->status === 'damaged';
    }

    /**
     * Reserve the serial number
     * 
     * @return bool
     */
    public function reserve(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->status = 'reserved';
        return $this->save();
    }

    /**
     * Release reservation
     * 
     * @return bool
     */
    public function releaseReservation(): bool
    {
        if (!$this->isReserved()) {
            return false;
        }

        $this->status = 'available';
        return $this->save();
    }

    /**
     * Issue the serial number to a staff member
     * 
     * @param int|null $holderId Staff ID who will hold the item
     * @param int|null $transactionId Associated ledger transaction
     * @return bool
     */
    public function issue(?int $holderId = null, ?int $transactionId = null): bool
    {
        if ($this->status === 'issued') {
            return false;
        }

        $this->status = 'issued';
        $this->issued_date = Carbon::now();
        $this->current_holder_id = $holderId;
        $this->last_transaction_id = $transactionId;

        return $this->save();
    }

    /**
     * Return the serial number (make available again)
     * 
     * @param int|null $transactionId Associated ledger transaction
     * @return bool
     */
    public function returnToStock(?int $transactionId = null): bool
    {
        if (!$this->isIssued()) {
            return false;
        }

        $this->status = 'available';
        $this->current_holder_id = null;
        $this->last_transaction_id = $transactionId;

        return $this->save();
    }

    /**
     * Mark as damaged
     * 
     * @param string $reason Damage reason
     * @return bool
     */
    public function markAsDamaged(string $reason): bool
    {
        $this->status = 'damaged';
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . 
                       'DAMAGED: ' . $reason . ' (' . Carbon::now()->toDateTimeString() . ')';
        
        return $this->save();
    }

    /**
     * Repair and return to available status
     * 
     * @param string $repairNotes Notes about the repair
     * @return bool
     */
    public function repair(string $repairNotes): bool
    {
        if (!$this->isDamaged()) {
            return false;
        }

        $this->status = 'available';
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . 
                       'REPAIRED: ' . $repairNotes . ' (' . Carbon::now()->toDateTimeString() . ')';
        
        return $this->save();
    }

    /**
     * Transfer to another holder
     * 
     * @param int $newHolderId New staff holder ID
     * @param int|null $transactionId Associated ledger transaction
     * @return bool
     */
    public function transferTo(int $newHolderId, ?int $transactionId = null): bool
    {
        if (!$this->isIssued()) {
            return false;
        }

        $oldHolderId = $this->current_holder_id;
        $this->current_holder_id = $newHolderId;
        $this->last_transaction_id = $transactionId;
        
        // TODO: Consider creating separate SerialNumberTransferHistory model
        // to avoid unbounded growth of notes field with frequent transfers
        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . 
                       'Transferred from holder #' . $oldHolderId . ' to #' . $newHolderId . 
                       ' (' . Carbon::now()->toDateTimeString() . ')';

        return $this->save();
    }

    /**
     * Get time since issue (in days)
     */
    public function getDaysSinceIssueAttribute(): ?int
    {
        if (!$this->issued_date) {
            return null;
        }

        return Carbon::now()->diffInDays($this->issued_date);
    }

    /**
     * Get time in stock (from received to issued, in days)
     */
    public function getTimeInStockAttribute(): ?int
    {
        if (!$this->received_date || !$this->issued_date) {
            return null;
        }

        return $this->issued_date->diffInDays($this->received_date);
    }

    /**
     * Get warehouse item options for dropdown
     */
    public function getWarehouseItemIdOptions(): array
    {
        return WarehouseItem::active()
            ->where('serial_tracking_enabled', true)
            ->with(['warehouse', 'purchaseable_item'])
            ->get()
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get staff options for dropdown (current holder)
     * 
     * TODO: This references Organization plugin's Staff model
     */
    public function getCurrentHolderIdOptions(): array
    {
        // TODO: Organization plugin reference
        // return \Omsb\Organization\Models\Staff::active()
        //     ->orderBy('name')
        //     ->pluck('display_name', 'id')
        //     ->toArray();
        return [];
    }
}
