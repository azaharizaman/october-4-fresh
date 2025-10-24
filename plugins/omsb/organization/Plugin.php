<?php namespace Omsb\Organization;

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
            'name' => 'Organization',
            'description' => 'Organization plugin to manage company sites and warehouses the staff with multi hierarchy structure.',
            'author' => 'Omsb',
            'icon' => 'icon-building',
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
            'Omsb\Organization\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'omsb.organization.some_permission' => [
                'tab' => 'Organization',
                'label' => 'Some permission'
            ],
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'organization' => [
                'label' => 'Organization',
                'url' => Backend::url('omsb/organization/SiteController'),
                'icon' => 'icon-leaf',
                'permissions' => ['omsb.organization.*'],
                'order' => 500,
            ],
        ];
    }
}
