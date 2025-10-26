<?php namespace Omsb\Feeder\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Feeds Backend Controller
 *
 * Manages the activity feed records in the backend
 *
 * @link https://docs.octobercms.com/4.x/extend/system/controllers.html
 */
class Feeds extends Controller
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
    public $requiredPermissions = ['omsb.feeder.access_feeds'];

    /**
     * __construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Omsb.Feeder', 'feeder', 'feeds');
    }
}
