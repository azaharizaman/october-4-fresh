<?php namespace App;

use Db;
use Log; 
use System\Classes\AppBase;


/**
 * Provider is an application level plugin, all registration methods are supported.
 */
class Provider extends AppBase
{
    /**
     * register method, called when the app is first registered.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }

    /**
     * boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // Db::listen(function ($query) {
        //     if (str_contains(strtolower($query->sql), 'omsb')) {
        //         Log::channel('sql')->info('SQL INSERT:', [
        //             'query' => $query->sql,
        //             'bindings' => $query->bindings,
        //             'time' => $query->time,
        //         ]);
        //     }
        // });

    }
}
