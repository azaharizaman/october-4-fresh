<?php namespace Omsb\Workflow;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/4.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Workflow',
            'description' => 'Workflow management system tracking workflow definitions, status transitions, and approval hierarchies for document-driven processes',
            'author' => 'Omsb',
            'icon' => 'icon-random'
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

        // return [
        //     'Omsb\Workflow\Components\MyComponent' => 'myComponent',
        // ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.workflow.access_all' => [
                'tab' => 'Workflow',
                'label' => 'Full Access to Workflow Management'
            ],
            'omsb.workflow.manage_definitions' => [
                'tab' => 'Workflow',
                'label' => 'Manage Workflow Definitions'
            ],
            'omsb.workflow.manage_statuses' => [
                'tab' => 'Workflow',
                'label' => 'Manage Document Statuses'
            ],
            'omsb.workflow.manage_transitions' => [
                'tab' => 'Workflow',
                'label' => 'Manage Status Transitions'
            ],
            'omsb.workflow.manage_roles' => [
                'tab' => 'Workflow',
                'label' => 'Manage Approver Roles'
            ],
            'omsb.workflow.view_history' => [
                'tab' => 'Workflow',
                'label' => 'View Workflow History'
            ]
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'workflow' => [
                'label' => 'Workflow',
                'url' => Backend::url('omsb/workflow/workflowdefinitions'),
                'icon' => 'icon-random',
                'permissions' => ['omsb.workflow.*'],
                'order' => 350,
                'sideMenu' => [
                    'workflow_definitions' => [
                        'label' => 'Workflow Definitions',
                        'icon' => 'icon-sitemap',
                        'url' => Backend::url('omsb/workflow/workflowdefinitions'),
                        'permissions' => ['omsb.workflow.manage_definitions']
                    ],
                    'document_statuses' => [
                        'label' => 'Document Statuses',
                        'icon' => 'icon-flag',
                        'url' => Backend::url('omsb/workflow/documentstatuses'),
                        'permissions' => ['omsb.workflow.manage_statuses']
                    ],
                    'status_transitions' => [
                        'label' => 'Status Transitions',
                        'icon' => 'icon-exchange',
                        'url' => Backend::url('omsb/workflow/statustransitions'),
                        'permissions' => ['omsb.workflow.manage_transitions']
                    ],
                    'separator1' => [
                        'label' => 'MANAGEMENT',
                        'counter' => false
                    ],
                    'approver_roles' => [
                        'label' => 'Approver Roles',
                        'icon' => 'icon-users',
                        'url' => Backend::url('omsb/workflow/approverroles'),
                        'permissions' => ['omsb.workflow.manage_roles']
                    ],
                    'workflow_history' => [
                        'label' => 'Workflow History',
                        'icon' => 'icon-history',
                        'url' => Backend::url('omsb/workflow/workflowhistory'),
                        'permissions' => ['omsb.workflow.view_history']
                    ]
                ]
            ]
        ];
    }
}
