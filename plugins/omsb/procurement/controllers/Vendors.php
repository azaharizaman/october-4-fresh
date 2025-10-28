<?php namespace Omsb\Procurement\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Vendors Backend Controller
 * 
 * Manages vendor records and related data
 */
class Vendors extends Controller
{
    /**
     * @var array Behaviors implemented by this controller
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class
    ];

    /**
     * @var string Configuration for FormController
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string Configuration for ListController
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var string Configuration for RelationController
     */
    public $relationConfig = 'config_relation.yaml';

    /**
     * @var array Permissions required to access this controller
     */
    public $requiredPermissions = ['omsb.procurement.vendors'];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Omsb.Procurement', 'procurement', 'vendors');
    }
}
