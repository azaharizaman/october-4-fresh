<?php namespace Omsb\Inventory\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Inventory Ledgers Backend Controller
 *
 * @link https://docs.octobercms.com/4.x/extend/system/controllers.html
 */
class InventoryLedgers extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var string formConfig file
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string listConfig file
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array required permissions
     */
    public $requiredPermissions = ['omsb.inventory.inventoryledgers'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Omsb.Inventory', 'inventory', 'inventoryledgers');
    }
}
