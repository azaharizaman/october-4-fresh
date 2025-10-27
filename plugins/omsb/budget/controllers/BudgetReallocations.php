<?php namespace Omsb\Budget\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Budget Reallocations Backend Controller
 *
 * @link https://docs.octobercms.com/4.x/extend/system/controllers.html
 */
class BudgetReallocations extends Controller
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
    public $requiredPermissions = ['omsb.budget.budget_reallocations'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Omsb.Budget', 'budget', 'budget_reallocations');
    }
}
