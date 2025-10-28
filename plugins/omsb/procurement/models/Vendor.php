<?php namespace Omsb\Procurement\Models;

use Model;
use BackendAuth;

/**
 * Vendor Model
 * 
 * Represents suppliers/vendors that provide goods and services
 *
 * @property int $id
 * @property string $code Unique vendor code
 * @property string $name Vendor name
 * @property string|null $registration_number Business registration number
 * @property \Carbon\Carbon|null $incorporation_date Date of incorporation
 * @property string|null $sap_code SAP system code
 * @property bool $is_bumi Bumiputera status
 * @property string|null $type Vendor type (Standard, Contractor, etc.)
 * @property string|null $category Vendor category
 * @property bool $is_specialized Specialized vendor flag
 * @property bool $is_precision Precision vendor flag
 * @property bool $is_approved Approved vendor flag
 * @property bool $is_gst GST registered flag
 * @property string|null $gst_number GST registration number
 * @property string|null $gst_type GST type
 * @property string|null $tax_number Tax identification number
 * @property bool $is_foreign Foreign vendor flag
 * @property int|null $country_id Country ID
 * @property int|null $origin_country_id Origin country ID
 * @property string|null $contact_person Contact person name
 * @property string|null $designation Contact person designation
 * @property string|null $contact_email Contact email
 * @property string|null $contact_phone Contact phone number
 * @property string|null $tel_no Telephone number
 * @property string|null $fax_no Fax number
 * @property string|null $hp_no Mobile number
 * @property string|null $email Email address
 * @property string|null $website Vendor website
 * @property string|null $street Street address
 * @property string|null $city City
 * @property int|null $state_id State ID
 * @property string|null $postcode Postcode
 * @property string|null $scope_of_work Scope of work description
 * @property string|null $service Services provided
 * @property float|null $credit_limit Credit limit amount
 * @property string|null $credit_terms Credit terms
 * @property \Carbon\Carbon|null $credit_updated_at Credit last updated date
 * @property string|null $credit_review Credit review status
 * @property string|null $credit_remarks Credit remarks
 * @property string $status Vendor status
 * @property string $payment_terms Payment terms
 * @property string|null $notes Additional notes
 * @property int|null $company_id Company ID (for multi-company setup)
 * @property int|null $address_id Primary address
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Vendor extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_procurement_vendors';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'registration_number',
        'incorporation_date',
        'sap_code',
        'is_bumi',
        'type',
        'category',
        'is_specialized',
        'is_precision',
        'is_approved',
        'is_gst',
        'gst_number',
        'gst_type',
        'tax_number',
        'is_foreign',
        'country_id',
        'origin_country_id',
        'contact_person',
        'designation',
        'contact_email',
        'contact_phone',
        'tel_no',
        'fax_no',
        'hp_no',
        'email',
        'website',
        'street',
        'city',
        'state_id',
        'postcode',
        'scope_of_work',
        'service',
        'credit_limit',
        'credit_terms',
        'credit_updated_at',
        'credit_review',
        'credit_remarks',
        'status',
        'payment_terms',
        'notes',
        'company_id',
        'address_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'registration_number',
        'incorporation_date',
        'sap_code',
        'type',
        'category',
        'gst_number',
        'gst_type',
        'tax_number',
        'country_id',
        'origin_country_id',
        'contact_person',
        'designation',
        'contact_email',
        'contact_phone',
        'tel_no',
        'fax_no',
        'hp_no',
        'email',
        'website',
        'street',
        'city',
        'state_id',
        'postcode',
        'scope_of_work',
        'service',
        'credit_limit',
        'credit_terms',
        'credit_updated_at',
        'credit_review',
        'credit_remarks',
        'notes',
        'company_id',
        'address_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_procurement_vendors,code',
        'name' => 'required|max:255',
        'status' => 'required',
        'payment_terms' => 'required|in:cod,net_15,net_30,net_45,net_60,net_90',
        'contact_email' => 'nullable|email',
        'email' => 'nullable|email',
        'address_id' => 'nullable|integer|exists:omsb_organization_addresses,id',
        'incorporation_date' => 'nullable|date',
        'credit_limit' => 'nullable|numeric|min:0',
        'credit_updated_at' => 'nullable|date',
        'is_bumi' => 'boolean',
        'is_specialized' => 'boolean',
        'is_precision' => 'boolean',
        'is_approved' => 'boolean',
        'is_gst' => 'boolean',
        'is_foreign' => 'boolean'
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'code.required' => 'Vendor code is required',
        'code.unique' => 'This vendor code is already in use',
        'name.required' => 'Vendor name is required',
        'status.required' => 'Vendor status is required',
        'payment_terms.required' => 'Payment terms are required',
        'contact_email.email' => 'Please enter a valid email address for contact email',
        'email.email' => 'Please enter a valid email address'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'incorporation_date',
        'credit_updated_at',
        'deleted_at'
    ];

    /**
     * @var array casts for attributes
     */
    protected $casts = [
        'is_bumi' => 'boolean',
        'is_specialized' => 'boolean',
        'is_precision' => 'boolean',
        'is_approved' => 'boolean',
        'is_gst' => 'boolean',
        'is_foreign' => 'boolean',
        'credit_limit' => 'decimal:2',
        'incorporation_date' => 'date',
        'credit_updated_at' => 'datetime'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'address' => [
            'Omsb\Organization\Models\Address'
        ]
    ];

    public $hasMany = [
        'purchase_orders' => [
            PurchaseOrder::class,
            'key' => 'vendor_id'
        ],
        'vendor_quotations' => [
            VendorQuotation::class,
            'key' => 'vendor_id'
        ],
        'purchaseable_items' => [
            PurchaseableItem::class,
            'key' => 'preferred_vendor_id'
        ],
        'shareholders' => [
            VendorShareholder::class,
            'key' => 'vendor_id'
        ]
    ];

    /**
     * Boot the model
     */
    public static function boot(): void
    {
        parent::boot();

        // Additional business logic can be added here
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Get payment terms label
     */
    public function getPaymentTermsLabelAttribute(): string
    {
        $labels = [
            'cod' => 'Cash on Delivery',
            'net_15' => 'Net 15 Days',
            'net_30' => 'Net 30 Days',
            'net_45' => 'Net 45 Days',
            'net_60' => 'Net 60 Days',
            'net_90' => 'Net 90 Days'
        ];
        
        return $labels[$this->payment_terms] ?? $this->payment_terms;
    }

    /**
     * Scope: Active vendors only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Bumiputera vendors only
     */
    public function scopeBumi($query)
    {
        return $query->where('is_bumi', true);
    }

    /**
     * Scope: Specialized vendors only
     */
    public function scopeSpecialized($query)
    {
        return $query->where('is_specialized', true);
    }

    /**
     * Scope: Approved vendors only
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: Filter by vendor type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if vendor is active
     */
    public function isActive(): bool
    {
        return strtolower($this->status) === 'active';
    }

    /**
     * Check if vendor is blacklisted
     */
    public function isBlacklisted(): bool
    {
        return strtolower($this->status) === 'blacklisted';
    }

    /**
     * Get vendor options for dropdown
     */
    public static function getVendorOptions(): array
    {
        return self::active()
            ->orderBy('name')
            ->pluck('display_name', 'id')
            ->toArray();
    }
}
