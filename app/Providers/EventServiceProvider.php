<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // 'App\Events\SomeEvent' => [
        //     'App\Listeners\EventListener',
        // ],
        'App\Events\UcenterEvent' => [
            'App\Listeners\UcenterListener'
        ],
        'App\Events\MessageEvent' => [
            'App\Listeners\MessageListener'
        ]
    ];
}
