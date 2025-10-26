<?php namespace Omsb\Registrar;

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
            'name' => 'Registrar',
            'description' => 'Document numbering patterns and registration management system with customizable auto-incrementing sequences',
            'author' => 'Omsb',
            'icon' => 'icon-list-ol'
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
            'Omsb\Registrar\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.registrar.access_all' => [
                'tab' => 'Registrar',
                'label' => 'Full Access to Document Registration'
            ],
            'omsb.registrar.manage_patterns' => [
                'tab' => 'Registrar',
                'label' => 'Manage Document Number Patterns'
            ],
            'omsb.registrar.manage_sequences' => [
                'tab' => 'Registrar',
                'label' => 'Manage Document Sequences'
            ],
            'omsb.registrar.view_registry' => [
                'tab' => 'Registrar',
                'label' => 'View Document Registry'
            ]
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'registrar' => [
                'label' => 'Registrar',
                'url' => Backend::url('omsb/registrar/documentpatterns'),
                'icon' => 'icon-list-ol',
                'permissions' => ['omsb.registrar.*'],
                'order' => 400,
                'sideMenu' => [
                    'document_patterns' => [
                        'label' => 'Document Patterns',
                        'icon' => 'icon-cog',
                        'url' => Backend::url('omsb/registrar/documentpatterns'),
                        'permissions' => ['omsb.registrar.manage_patterns']
                    ],
                    'document_sequences' => [
                        'label' => 'Document Sequences',
                        'icon' => 'icon-sort-numeric-asc',
                        'url' => Backend::url('omsb/registrar/documentsequences'),
                        'permissions' => ['omsb.registrar.manage_sequences']
                    ],
                    'document_registry' => [
                        'label' => 'Document Registry',
                        'icon' => 'icon-book',
                        'url' => Backend::url('omsb/registrar/documentregistry'),
                        'permissions' => ['omsb.registrar.view_registry']
                    ]
                ]
            ]
        ];
    }
}
