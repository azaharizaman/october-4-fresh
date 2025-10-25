<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Site Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Site extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_sites';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'code',
        'name',
        'tel_no',
        'fax_no',
        'type',
        'parent_id',
        'company_id',
        'address_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'parent_id',
        'company_id',
        'address_id'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [];

    /**
     * @var array dates used by the model
     */
    protected $dates = [
        'deleted_at'
    ];

    // Relationships
    public $belongsTo = [
        'parent' => [Site::class, 'key' => 'parent_id'],
        'company' => [Company::class],
        'address' => [Address::class]
    ];

    public $hasMany = [
        'children' => [Site::class, 'key' => 'parent_id'],
        'warehouses' => [\Omsb\Inventory\Models\Warehouse::class]
    ];
}
