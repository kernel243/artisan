<?php

namespace Kernel243\Artisan;

use Illuminate\Support\ServiceProvider;
use Kernel243\Artisan\Commands\Repository;
use Kernel243\Artisan\Commands\Service;

class CommandServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Repository::class,
                Service::class
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

}
