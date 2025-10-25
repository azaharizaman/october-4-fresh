<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Address Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Address extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_addresses';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'address_street',
        'address_city',
        'address_state',
        'address_postcode',
        'address_country',
        'region',
        'company_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'company_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'address_street' => 'required',
        'address_city' => 'required',
        'address_state' => 'required',
        'address_postcode' => 'required',
        'address_country' => 'required',
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
        'company' => [
            Company::class,
            'key' => 'company_id'
        ]
    ];


    /**
     * Get formatted address string
     */
    public function getFullAddressAttribute(): string
    {
        return trim(implode(', ', array_filter([
            $this->address_street,
            $this->address_city,
            $this->address_state,
            $this->address_postcode,
            $this->address_country
        ])));
    }
}
