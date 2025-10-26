<?php namespace Omsb\Feeder;

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
            'name' => 'Feeder',
            'description' => 'Activity tracking plugin that logs all user actions across the system',
            'author' => 'Omsb',
            'icon' => 'icon-rss'
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
            'Omsb\Feeder\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'omsb.feeder.access_feeds' => [
                'tab' => 'Feeder',
                'label' => 'Access Activity Feed'
            ],
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'feeder' => [
                'label' => 'Activity Feed',
                'url' => Backend::url('omsb/feeder/feeds'),
                'icon' => 'icon-rss',
                'permissions' => ['omsb.feeder.*'],
                'order' => 500,
            ],
        ];
    }
}
