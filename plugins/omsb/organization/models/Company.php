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
