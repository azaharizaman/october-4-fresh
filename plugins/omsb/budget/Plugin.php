<?php namespace Omsb\Budget;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 *
 * Budget Plugin - Manages yearly budgets with support for budget transactions
 * Budgets are entered yearly before the end of the current year for the next year's budget
 *
 * @link https://docs.octobercms.com/4.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * @var array Plugin dependencies
     */
    public $require = [
        'Omsb.Organization',
        'Omsb.Registrar'
    ];

    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Budget',
            'description' => 'Budget management system for yearly budget planning with transactions (transfers, adjustments, reallocations) and budget utilization tracking',
            'author' => 'Omsb',
            'icon' => 'icon-calculator'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        //
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        //
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Omsb\Budget\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.budget.access_all' => [
                'tab' => 'Budget',
                'label' => 'Full Access to Budget Management'
            ],
            'omsb.budget.manage_budgets' => [
                'tab' => 'Budget',
                'label' => 'Manage Budgets'
            ],
            'omsb.budget.budget_transfers' => [
                'tab' => 'Budget',
                'label' => 'Manage Budget Transfers'
            ],
            'omsb.budget.budget_adjustments' => [
                'tab' => 'Budget',
                'label' => 'Manage Budget Adjustments'
            ],
            'omsb.budget.budget_reallocations' => [
                'tab' => 'Budget',
                'label' => 'Manage Budget Reallocations'
            ],
            'omsb.budget.view_reports' => [
                'tab' => 'Budget',
                'label' => 'View Budget Reports'
            ]
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'budget' => [
                'label' => 'Budget',
                'url' => Backend::url('omsb/budget/budgets'),
                'icon' => 'icon-calculator',
                'permissions' => ['omsb.budget.*'],
                'order' => 300,
                'sideMenu' => [
                    'budgets' => [
                        'label' => 'Budgets',
                        'icon' => 'icon-money',
                        'url' => Backend::url('omsb/budget/budgets'),
                        'permissions' => ['omsb.budget.manage_budgets']
                    ],
                    'separator1' => [
                        'label' => 'TRANSACTIONS',
                        'counter' => false
                    ],
                    'budget_transfers' => [
                        'label' => 'Budget Transfers',
                        'icon' => 'icon-exchange',
                        'url' => Backend::url('omsb/budget/budgettransfers'),
                        'permissions' => ['omsb.budget.budget_transfers']
                    ],
                    'budget_adjustments' => [
                        'label' => 'Budget Adjustments',
                        'icon' => 'icon-edit',
                        'url' => Backend::url('omsb/budget/budgetadjustments'),
                        'permissions' => ['omsb.budget.budget_adjustments']
                    ],
                    'budget_reallocations' => [
                        'label' => 'Budget Reallocations',
                        'icon' => 'icon-random',
                        'url' => Backend::url('omsb/budget/budgetreallocations'),
                        'permissions' => ['omsb.budget.budget_reallocations']
                    ],
                    'separator2' => [
                        'label' => 'REPORTS',
                        'counter' => false
                    ],
                    'reports' => [
                        'label' => 'Budget Reports',
                        'icon' => 'icon-bar-chart',
                        'url' => Backend::url('omsb/budget/reports'),
                        'permissions' => ['omsb.budget.view_reports']
                    ]
                ]
            ]
        ];
    }
}
