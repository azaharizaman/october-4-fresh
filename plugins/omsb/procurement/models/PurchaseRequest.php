<?php namespace Omsb\Procurement\Models;

use Model;
use BackendAuth;

/**
 * PurchaseRequest Model
 * 
 * Purchase request document
 *
 * @property int $id
 * @property string $document_number Unique document number
 * @property \Carbon\Carbon $request_date Request date
 * @property \Carbon\Carbon $required_date Required by date
 * @property string $priority Request priority (low, normal, high, urgent)
 * @property string $status Document status
 * @property string $purpose Purpose of request
 * @property string|null $justification Request justification
 * @property string|null $notes Additional notes
 * @property float $total_amount Total amount
 * @property int $site_id Requesting site
 * @property int $requested_by Requesting staff
 * @property int|null $created_by Backend user who created this
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class PurchaseRequest extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Omsb\Workflow\Traits\HasWorkflow;
    use \Omsb\Feeder\Traits\HasFeed;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_purchase_requests';

    /**
     * Workflow configuration
     */
    protected $workflowDocumentType = 'purchase_request';
    protected $workflowEligibleStatuses = ['draft'];
    protected $workflowPendingStatus = 'submitted';
    protected $workflowApprovedStatus = 'approved';
    protected $workflowRejectedStatus = 'rejected';
    
    /**
     * Allow total_amount to be updated during workflow (for automatic recalculation)
     * This prevents ValidationException when line items are modified
     */
    protected $workflowAllowedFields = ['total_amount'];

    /**
     * HasFeed trait configuration
     */
    protected $feedMessageTemplate = '{actor} {action} Purchase Request {model_identifier} ({status})';
    protected $feedableActions = ['created', 'updated', 'deleted', 'submitted', 'approved', 'rejected', 'cancelled'];
    protected $feedSignificantFields = ['status', 'total_amount', 'priority', 'required_date'];

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'document_number',
        'request_date',
        'required_date',
        'priority',
        'status',
        'purpose',
        'justification',
        'notes',
        'total_amount',
        'site_id',
        'requested_by',
        'service_code',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejection_reason'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'justification',
        'notes',
        'service_code',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'document_number' => 'required|unique:omsb_procurement_purchase_requests,document_number',
        'request_date' => 'required|date',
        'required_date' => 'required|date|after_or_equal:request_date',
        'priority' => 'required|in:low,normal,high,urgent',
        'status' => 'required|in:draft,submitted,reviewed,approved,rejected,cancelled,completed',
        'purpose' => 'required|max:255',
        'service_code' => 'nullable|max:10',
        'total_amount' => 'required|numeric|min:0',
        'site_id' => 'required|integer|exists:omsb_organization_sites,id',
        'requested_by' => 'required|integer|exists:omsb_organization_staff,id'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'request_date',
        'required_date',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'deleted_at'
    ];

    /**
     * @var array Casts for attributes
     */
    protected $casts = [
        'total_amount' => 'decimal:2'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'site' => [
            'Omsb\Organization\Models\Site'
        ],
        'requester' => [
            'Omsb\Organization\Models\Staff',
            'key' => 'requested_by'
        ],
        'submitter' => [
            \Backend\Models\User::class,
            'key' => 'submitted_by'
        ],
        'reviewer' => [
            \Backend\Models\User::class,
            'key' => 'reviewed_by'
        ],
        'approver' => [
            \Backend\Models\User::class,
            'key' => 'approved_by'
        ],
        'creator' => [
            \Backend\Models\User::class,
            'key' => 'created_by'
        ]
    ];

    public $hasMany = [
        'items' => [
            PurchaseRequestItem::class,
            'key' => 'purchase_request_id',
            'order' => 'line_number'
        ],
        'vendor_quotations' => [
            VendorQuotation::class,
            'key' => 'purchase_request_id'
        ],
        'purchase_orders' => [
            PurchaseOrder::class,
            'key' => 'purchase_request_id'
        ]
    ];

    public $morphMany = [
        'feeds' => [
            'Omsb\Feeder\Models\Feed',
            'name' => 'feedable'
        ]
        // workflow_instances relationship is now provided by HasWorkflow trait
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
            
            // Set default request date
            if (!$model->request_date) {
                $model->request_date = now();
            }
        });

        // Recalculate total when items change
        static::saving(function ($model) {
            $model->recalculateTotal();
        });
    }

    /**
     * Recalculate total amount from line items
     */
    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('estimated_total_cost');
        
        if ($this->total_amount != $total) {
            $this->total_amount = $total;
            // Use updateQuietly to avoid triggering events (prevents infinite loop)
            // This bypasses the saving event and workflow protection
            if ($this->exists) {
                $this->updateQuietly(['total_amount' => $total]);
            }
        }
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->document_number . ' (' . $this->purpose . ')';
    }

    /**
     * Get status label with color
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'reviewed' => 'Reviewed',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed'
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];
        
        return $labels[$this->priority] ?? $this->priority;
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by site
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if document is editable
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if document can be deleted
     */
    public function isDeletable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if document can be submitted
     */
    public function canSubmit(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    /**
     * Get site options for dropdown
     */
    public function getSiteIdOptions(): array
    {
        return \Omsb\Organization\Models\Site::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Get requester options for dropdown
     */
    public function getRequestedByOptions(): array
    {
        return \Omsb\Organization\Models\Staff::active()
            ->orderBy('first_name')
            ->get()
            ->pluck('full_name', 'id')
            ->toArray();
    }

    /**
     * Get service details for this purchase request
     */
    public function getServiceAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceByCode($this->service_code);
    }

    /**
     * Get service name
     */
    public function getServiceNameAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceName($this->service_code);
    }

    /**
     * Get service color
     */
    public function getServiceColorAttribute()
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceColor($this->service_code);
    }

    /**
     * Get service code options for dropdown
     */
    public function getServiceCodeOptions(): array
    {
        return \Omsb\Organization\Models\ServiceSettings::getServiceDropdownOptions();
    }

    /**
     * Scope: Filter by service
     */
    public function scopeByService($query, $serviceCode)
    {
        return $query->where('service_code', $serviceCode);
    }

    /**
     * Check if request belongs to specific service
     */
    public function belongsToService($serviceCode)
    {
        return $this->service_code === $serviceCode;
    }

    /**
     * Get approval threshold for this request's service
     */
    public function getServiceApprovalThreshold()
    {
        return \Omsb\Organization\Models\ServiceSettings::getApprovalThreshold($this->service_code);
    }

    /**
     * Check if this request requires special approval due to amount/service combination
     */
    public function requiresSpecialApproval()
    {
        return \Omsb\Organization\Models\ServiceSettings::requiresSpecialApproval($this->service_code, $this->total_amount);
    }
}
