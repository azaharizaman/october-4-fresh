<?php namespace Omsb\Organization\Models;

use Model;

/**
 * Staff Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class Staff extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_organization_staff';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'staff_number',
        'is_manager',
        'date_join',
        'date_resigned',
        'position',
        'qualification',
        'contact_no',
        'user_id',
        'site_id',
        'company_id'
    ];

    /**
     * @var array attributes that should be converted to null when empty
     */
    protected $nullable = [
        'user_id',
        'site_id',
        'company_id'
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
}
