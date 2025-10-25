<?php namespace Omsb\Inventory\Models;

use Model;

/**
 * WarehouseItemUOM Model
 *
 * @link https://docs.octobercms.com/4.x/extend/system/models.html
 */
class WarehouseItemUOM extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string table name
     */
    public $table = 'omsb_inventory_warehouse_item_u_o_ms';

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
