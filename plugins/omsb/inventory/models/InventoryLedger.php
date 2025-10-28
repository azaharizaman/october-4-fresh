<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Carbon\Carbon;

/**
 * InventoryLedger Model
 * 
 * Double-entry inventory tracking system.
 * Every increase must have a corresponding decrease (or vice versa).
 * Immutable once created - provides audit trail.
 *
 * @property int $id
 * @property int $warehouse_item_id Affected warehouse item (SKU)
 * @property int|null $base_uom_id Base UOM (matches WarehouseItem base_uom_id)
 * @property string $document_type Source document class (morphTo)
 * @property int $document_id Source document ID
 * @property string $transaction_type Type (receipt, issue, adjustment, transfer_in, transfer_out)
 * @property float $quantity_change +/- quantity value (ALWAYS in base UOM)
 * @property float $quantity_before Balance before transaction (ALWAYS in base UOM)
 * @property float $quantity_after Balance after transaction (ALWAYS in base UOM)
 * @property float|null $unit_cost Cost per unit for valuation
 * @property float|null $total_cost quantity × unit_cost
 * @property string|null $reference_number Document reference
 * @property \Carbon\Carbon $transaction_date When transaction occurred
 * @property string|null $notes Additional context
 * @property bool $is_locked Prevents modification after month-end
 * @property int $transaction_uom_id UOM used in transaction (legacy)
 * @property int|null $original_transaction_uom_id Original UOM used in transaction (audit trail)
 * @property float|null $original_transaction_quantity Original quantity in transaction UOM (audit trail)
 * @property float $quantity_in_transaction_uom Actual qty in transaction UOM
 * @property float $quantity_in_default_uom Converted qty for reporting
 * @property float $conversion_factor_used Audit trail of conversion rate
 * @property int|null $inventory_period_id Associated period
 * @property int $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class InventoryLedger extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_inventory_ledgers';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'warehouse_item_id',
        'base_uom_id',
        'document_type',
        'document_id',
        'transaction_type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'total_cost',
        'reference_number',
        'transaction_date',
        'notes',
        'is_locked',
        'transaction_uom_id',
        'original_transaction_uom_id',
        'original_transaction_quantity',
        'quantity_in_transaction_uom',
        'quantity_in_default_uom',
        'conversion_factor_used',
        'inventory_period_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'base_uom_id',
        'unit_cost',
        'total_cost',
        'reference_number',
        'notes',
        'original_transaction_uom_id',
        'original_transaction_quantity',
        'inventory_period_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'warehouse_item_id' => 'required|integer|exists:omsb_inventory_warehouse_items,id',
        'base_uom_id' => 'nullable|integer|exists:omsb_organization_unit_of_measures,id',
        'document_type' => 'required|string',
        'document_id' => 'required|integer',
        'transaction_type' => 'required|in:receipt,issue,adjustment,transfer_in,transfer_out',
        'quantity_change' => 'required|numeric',
        'quantity_before' => 'required|numeric',
        'quantity_after' => 'required|numeric',
        'unit_cost' => 'nullable|numeric|min:0',
        'total_cost' => 'nullable|numeric',
        'transaction_date' => 'required|date',
        'transaction_uom_id' => 'required|integer|exists:omsb_inventory_unit_of_measures,id',
        'original_transaction_uom_id' => 'nullable|integer|exists:omsb_organization_unit_of_measures,id',
        'original_transaction_quantity' => 'nullable|numeric',
        'quantity_in_transaction_uom' => 'required|numeric',
        'quantity_in_default_uom' => 'required|numeric',
        'conversion_factor_used' => 'required|numeric|min:0.000001',
        'is_locked' => 'boolean'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'warehouse_item_id.required' => 'Warehouse item is required',
        'transaction_type.required' => 'Transaction type is required',
        'transaction_type.in' => 'Invalid transaction type',
        'quantity_change.required' => 'Quantity change is required',
        'transaction_date.required' => 'Transaction date is required'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'transaction_date'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'quantity_change' => 'decimal:6',
        'quantity_before' => 'decimal:6',
        'quantity_after' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'quantity_in_transaction_uom' => 'decimal:6',
        'quantity_in_default_uom' => 'decimal:6',
        'conversion_factor_used' => 'decimal:6',
        'is_locked' => 'boolean'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse_item' => [
            WarehouseItem::class
        ],
        'base_uom' => [
            'Omsb\Organization\Models\UnitOfMeasure',
            'key' => 'base_uom_id'
        ],
        'transaction_uom' => [
            UnitOfMeasure::class,
            'key' => 'transaction_uom_id'
        ],
        'original_transaction_uom' => [
            'Omsb\Organization\Models\UnitOfMeasure',
            'key' => 'original_transaction_uom_id'
        ],
        'inventory_period' => [
            InventoryPeriod::class
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
        'document' => []
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

            // Auto-calculate total cost if unit cost is provided
            if ($model->unit_cost && !$model->total_cost) {
                $model->total_cost = abs($model->quantity_change) * $model->unit_cost;
            } elseif ($model->unit_cost && $model->total_cost) {
                $expectedTotalCost = abs($model->quantity_change) * $model->unit_cost;
                // Use bccomp for floating point comparison with 4 decimal places
                if (bccomp((string)$model->total_cost, (string)$expectedTotalCost, 4) !== 0) {
                    throw new \Exception('Provided total_cost does not match abs(quantity_change) * unit_cost');
                }
            }
        });

        // Prevent updates to locked entries
        static::updating(function ($model) {
            if ($model->getOriginal('is_locked')) {
                throw new \Exception('Cannot modify locked ledger entry');
            }
        });

        // Prevent deletion (immutable audit trail)
        static::deleting(function ($model) {
            throw new \Exception('Ledger entries cannot be deleted');
        });
    }

    /**
     * Get display name for the ledger entry
     */
    public function getDisplayNameAttribute(): string
    {
        $type = ucfirst(str_replace('_', ' ', $this->transaction_type));
        $qty = number_format(abs($this->quantity_change), 2);
        $uom = $this->transaction_uom ? $this->transaction_uom->code : '';
        
        return sprintf(
            '%s: %s%s %s',
            $type,
            $this->quantity_change >= 0 ? '+' : '-',
            $qty,
            $uom
        );
    }

    /**
     * Scope: Active (unlocked) entries
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Scope: Locked entries
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Scope: Filter by warehouse item
     */
    public function scopeForItem($query, int $warehouseItemId)
    {
        return $query->where('warehouse_item_id', $warehouseItemId);
    }

    /**
     * Scope: Filter by transaction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Receipts only (positive quantity changes)
     */
    public function scopeReceipts($query)
    {
        return $query->where('quantity_change', '>', 0);
    }

    /**
     * Scope: Issues only (negative quantity changes)
     */
    public function scopeIssues($query)
    {
        return $query->where('quantity_change', '<', 0);
    }

    /**
     * Scope: Filter by period
     */
    public function scopeInPeriod($query, int $periodId)
    {
        return $query->where('inventory_period_id', $periodId);
    }

    /**
     * Check if entry can be modified
     */
    public function canModify(): bool
    {
        return !$this->is_locked;
    }

    /**
     * Lock the entry (month-end closing)
     */
    public function lock(): bool
    {
        if ($this->is_locked) {
            return false;
        }

        $this->is_locked = true;
        return $this->save();
    }

    /**
     * Get transaction direction (IN or OUT)
     */
    public function getDirectionAttribute(): string
    {
        return $this->quantity_change >= 0 ? 'IN' : 'OUT';
    }

    /**
     * Check if this is a receipt transaction
     */
    public function isReceipt(): bool
    {
        return $this->quantity_change > 0;
    }

    /**
     * Check if this is an issue transaction
     */
    public function isIssue(): bool
    {
        return $this->quantity_change < 0;
    }

    /**
     * Get absolute quantity value
     */
    public function getAbsoluteQuantityAttribute(): float
    {
        return abs($this->quantity_change);
    }

    /**
     * Create a ledger entry (factory method)
     * Wraps the operation in a transaction with row-level locking to prevent race conditions
     * 
     * @param array $data Ledger entry data
     * @return self
     */
    public static function createEntry(array $data): self
    {
        // Ensure required fields
        if (!isset($data['warehouse_item_id']) || !isset($data['quantity_change'])) {
            throw new \InvalidArgumentException('warehouse_item_id and quantity_change are required');
        }

        // Wrap in transaction with row-level locking to prevent race conditions
        return \DB::transaction(function () use ($data) {
            // Get current balance with row-level lock
            $warehouseItem = WarehouseItem::where('id', $data['warehouse_item_id'])
                ->lockForUpdate()
                ->first();
                
            if (!$warehouseItem) {
                throw new \InvalidArgumentException('Invalid warehouse item ID');
            }

            $data['quantity_before'] = $warehouseItem->quantity_on_hand;
            $data['quantity_after'] = $data['quantity_before'] + $data['quantity_change'];

            // Set transaction date to now if not provided
            if (!isset($data['transaction_date'])) {
                $data['transaction_date'] = Carbon::now();
            }

            // Create the ledger entry
            $entry = new self($data);
            $entry->save();

            // Update warehouse item quantity
            $warehouseItem->quantity_on_hand = $data['quantity_after'];
            $warehouseItem->save();

            return $entry;
        });
    }
}
