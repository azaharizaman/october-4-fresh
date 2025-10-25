<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * MrnReturnItem Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class MrnReturnItem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_mrn_return_items';

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
