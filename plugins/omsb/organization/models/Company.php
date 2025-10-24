<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Company Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Company extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_companies';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'logo',
        'parent_id',
        'address_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'code' => 'required|unique:omsb_organization_companies,code',
        'name' => 'required|min:3',
    ];

    /**
     * @var array custom validation messages
     */
    public $customMessages = [
        'code.required' => 'Company code is required',
        'code.unique' => 'Company code must be unique',
        'name.required' => 'Company name is required',
        'name.min' => 'Company name must be at least 3 characters',
    ];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];

    /**
     * @var array belongsTo relationships
     */
    public $belongsTo = [
        'parent' => [
            Company::class,
            'key' => 'parent_id'
        ],
        'address' => [
            Address::class,
            'key' => 'address_id'
        ]
    ];

    /**
     * @var array hasMany relationships
     */
    public $hasMany = [
        'children' => [
            Company::class,
            'key' => 'parent_id'
        ],
        'addresses' => [
            Address::class,
            'key' => 'company_id'
        ],
        'sites' => [
            Site::class,
            'key' => 'company_id'
        ]
    ];

    public $attachOne = [
        'logo' => [
            \System\Models\File::class
        ]
    ];

    public $belongsToMany = [
        'addresses' => [
            Address::class,
            'table' => 'omsb_organization_company_addresses',
            'pivot' => ['is_mailing', 'is_administrative', 'is_receiving_goods', 'is_billing', 'is_registered_office', 'is_primary', 'is_active', 'effective_from', 'effective_to', 'notes']
        ]
    ];
    
    // Helper methods
    public function getPrimaryAddress()
    {
        return $this->addresses()->wherePivot('is_primary', true)->first();
    }
    
    public function getMailingAddress()
    {
        return $this->addresses()->wherePivot('is_mailing', true)->first();
    }
    
    public function getReceivingAddress()
    {
        return $this->addresses()->wherePivot('is_receiving_goods', true)->first();
    }

    /**
     * Get the display name for the company
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Options for parent company dropdown
     */
    public function getParentIdOptions(): array
    {
        // Exclude self and children to prevent circular references
        return self::where('id', '!=', $this->id ?? 0)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Options for address dropdown (only addresses belonging to this company)
     */
    public function getAddressIdOptions(): array
    {
        if (!$this->id) {
            return [];
        }

        return $this->addresses()
            ->orderBy('address_city')
            ->get()
            ->mapWithKeys(function ($address) {
                $label = $address->address_street . ', ' . 
                         $address->address_city . ', ' . 
                         $address->address_state . ' ' . 
                         $address->address_postcode;
                return [$address->id => $label];
            })
            ->all();
    }
}
