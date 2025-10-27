<?php namespace Omsb\Inventory\Models;

use Model;
use BackendAuth;
use Omsb\Registrar\Traits\HasControlledDocumentNumber;
use Carbon\Carbon;
use ValidationException;

/**
 * Mri Model - Material Request Issuance
 * 
 * Records material/stock issuance from warehouses to requesters.
 * Reduces WarehouseItem quantities and creates InventoryLedger entries.
 * Supports various issue purposes (maintenance, operation, project, etc.)
 *
 * @property int $id
 * @property string $mri_number Unique document number
 * @property int $warehouse_id Issuing warehouse
 * @property int $requested_by Staff who requested the materials
 * @property int|null $issued_by Staff who issued the materials
 * @property int|null $approved_by Staff who approved the MRI
 * @property int|null $requesting_site_id Department/Site requesting materials
 * @property \Carbon\Carbon $issue_date Date materials were issued
 * @property \Carbon\Carbon|null $requested_date When initially requested
 * @property string $issue_purpose Purpose of issue (maintenance, operation, project, etc.)
 * @property string|null $cost_center For cost allocation
 * @property string|null $project_code If project-related
 * @property string|null $remarks Additional notes
 * @property float $total_issue_value Total value of goods issued
 * @property string $status Document status (draft, submitted, approved, completed)
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Mri extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use HasControlledDocumentNumber;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mris';

    /**
     * @var string document type code for registrar
     */
    protected string $documentTypeCode = 'MRI';

    /**
     * @var array statuses that lock the document
     */
    protected array $protectedStatuses = ['approved', 'completed'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'mri_number',
        'document_number',
        'registry_id',
        'warehouse_id',
        'requested_by',
        'issued_by',
        'approved_by',
        'requesting_site_id',
        'issue_date',
        'requested_date',
        'issue_purpose',
        'cost_center',
        'project_code',
        'remarks',
        'total_issue_value',
        'status'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'issued_by',
        'approved_by',
        'requesting_site_id',
        'requested_date',
        'cost_center',
        'project_code',
        'remarks',
        'created_by',
        'registry_id',
        'previous_status'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'mri_number' => 'required|max:255|unique:omsb_inventory_mris,mri_number',
        'warehouse_id' => 'required|integer|exists:omsb_inventory_warehouses,id',
        'requested_by' => 'required|integer|exists:omsb_organization_staff,id',
        'issued_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'approved_by' => 'nullable|integer|exists:omsb_organization_staff,id',
        'requesting_site_id' => 'nullable|integer|exists:omsb_organization_sites,id',
        'issue_date' => 'required|date',
        'requested_date' => 'nullable|date|before_or_equal:issue_date',
        'issue_purpose' => 'required|in:maintenance,operation,project,construction,repair,installation,other',
        'cost_center' => 'nullable|max:100',
        'project_code' => 'nullable|max:100',
        'total_issue_value' => 'numeric|min:0',
        'status' => 'required|in:draft,submitted,approved,completed'
    ];

    /**
     * @var array Validation custom messages
     */
    public $customMessages = [
        'mri_number.required' => 'MRI number is required',
        'mri_number.unique' => 'This MRI number is already in use',
        'warehouse_id.required' => 'Warehouse is required',
        'warehouse_id.exists' => 'Selected warehouse does not exist',
        'requested_by.required' => 'Requested by staff is required',
        'requested_by.exists' => 'Selected staff does not exist',
        'issue_date.required' => 'Issue date is required',
        'requested_date.before_or_equal' => 'Requested date must be before or equal to issue date',
        'issue_purpose.required' => 'Issue purpose is required',
        'issue_purpose.in' => 'Invalid issue purpose',
        'status.required' => 'Status is required',
        'status.in' => 'Invalid status',
        'total_issue_value.min' => 'Total value cannot be negative'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'issue_date',
        'requested_date',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'total_issue_value' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'warehouse' => [
            Warehouse::class
        ],
        'requester' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'requested_by'
        ],
        'issuer' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'issued_by'
        ],
        'approver' => [
            // TODO: Reference to Organization plugin - Staff model
            'Omsb\Organization\Models\Staff',
            'key' => 'approved_by'
        ],
        'requesting_site' => [
            // TODO: Reference to Organization plugin - Site model
            'Omsb\Organization\Models\Site'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'items' => [
            MriItem::class,
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
            
            // Default issue_date to today if not set
            if (!$model->issue_date) {
                $model->issue_date = Carbon::today();
            }
        });

        // Prevent modification of non-draft documents
        static::updating(function ($model) {
            if ($model->getOriginal('status') !== 'draft' && $model->isDirty(['warehouse_id', 'requested_by'])) {
                throw new ValidationException([
                    'status' => 'Cannot modify key fields of a non-draft MRI'
                ]);
            }
        });

        // Calculate total on save
        static::saving(function ($model) {
            if ($model->items) {
                $model->total_issue_value = $model->items->sum('total_cost');
            }
        });

        // Prevent deletion of approved/completed documents
        static::deleting(function ($model) {
            if (in_array($model->status, ['approved', 'completed'])) {
                throw new ValidationException([
                    'status' => 'Cannot delete an approved or completed MRI'
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
            $this->mri_number,
            $this->issue_date->format('d M Y'),
            strtoupper($this->status)
        );
    }

    /**
     * Check if MRI can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if MRI can be approved
     */
    public function canApprove(): bool
    {
        return in_array($this->status, ['submitted', 'draft']);
    }

    /**
     * Check if MRI can be completed
     */
    public function canComplete(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if MRI is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if MRI is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if MRI is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if MRI is completed
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
                'status' => 'Only draft MRIs can be submitted'
            ]);
        }

        if ($this->items->isEmpty()) {
            throw new ValidationException([
                'items' => 'Cannot submit an MRI without line items'
            ]);
        }

        $this->status = 'submitted';
        return $this->save();
    }

    /**
     * Approve the MRI
     */
    public function approve(int $approvedBy = null): bool
    {
        if (!$this->canApprove()) {
            throw new ValidationException([
                'status' => 'This MRI cannot be approved'
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
     * Complete the MRI (finalize and update inventory)
     * 
     * This creates InventoryLedger entries and updates WarehouseItem quantities
     */
    public function complete(): bool
    {
        if (!$this->canComplete()) {
            throw new ValidationException([
                'status' => 'Only approved MRIs can be completed'
            ]);
        }

        // TODO: Validate stock availability before completion
        // foreach ($this->items as $item) {
        //     if ($item->issued_quantity > $item->warehouse_item->quantity_on_hand) {
        //         throw new ValidationException([...]);
        //     }
        // }

        // TODO: Create InventoryLedger entries via InventoryLedgerService
        // foreach ($this->items as $item) {
        //     InventoryLedgerService::createIssueEntry([...]);
        // }

        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Reject the MRI
     */
    public function reject(string $reason = null): bool
    {
        if ($this->isCompleted()) {
            throw new ValidationException([
                'status' => 'Cannot reject a completed MRI'
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
     * Scope: Draft MRIs
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Submitted MRIs
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope: Approved MRIs
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Completed MRIs
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
     * Scope: By issue purpose
     */
    public function scopeByPurpose($query, string $purpose)
    {
        return $query->where('issue_purpose', $purpose);
    }

    /**
     * Scope: By date range
     */
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('issue_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Recent MRIs (within days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('issue_date', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope: By project
     */
    public function scopeByProject($query, string $projectCode)
    {
        return $query->where('project_code', $projectCode);
    }

    /**
     * Scope: By cost center
     */
    public function scopeByCostCenter($query, string $costCenter)
    {
        return $query->where('cost_center', $costCenter);
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

    /**
     * Get issue purpose options for dropdowns
     */
    public function getIssuePurposeOptions(): array
    {
        return [
            'maintenance' => 'Maintenance',
            'operation' => 'Operation',
            'project' => 'Project',
            'construction' => 'Construction',
            'repair' => 'Repair',
            'installation' => 'Installation',
            'other' => 'Other'
        ];
    }
}
