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
 * @property string|null $tax_number Tax identification number
 * @property string|null $contact_person Contact person name
 * @property string|null $contact_email Contact email
 * @property string|null $contact_phone Contact phone number
 * @property string|null $website Vendor website
 * @property string $status Vendor status (active, inactive, blacklisted)
 * @property string $payment_terms Payment terms
 * @property string|null $notes Additional notes
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
        'tax_number',
        'contact_person',
        'contact_email',
        'contact_phone',
        'website',
        'status',
        'payment_terms',
        'notes',
        'address_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'registration_number',
        'tax_number',
        'contact_person',
        'contact_email',
        'contact_phone',
        'website',
        'notes',
        'address_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|max:255|unique:omsb_procurement_vendors,code',
        'name' => 'required|max:255',
        'status' => 'required|in:active,inactive,blacklisted',
        'payment_terms' => 'required|in:cod,net_15,net_30,net_45,net_60,net_90',
        'contact_email' => 'nullable|email',
        'address_id' => 'nullable|integer|exists:omsb_organization_addresses,id'
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
        'contact_email.email' => 'Please enter a valid email address'
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
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
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if vendor is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if vendor is blacklisted
     */
    public function isBlacklisted(): bool
    {
        return $this->status === 'blacklisted';
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
