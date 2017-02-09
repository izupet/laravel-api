<?php

namespace Izupet\Api\Providers;

use Illuminate\Support\ServiceProvider;
use Izupet\Api\Console\Commands\ApiRequest;

class ApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/api.php' => config_path('api.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiRequest::class
            ]);
        }
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
    }
}
