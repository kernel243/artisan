<?php

namespace Kernel243\Artisan;

use Illuminate\Support\ServiceProvider;
use Kernel243\Artisan\Commands\File;
use Kernel243\Artisan\Commands\Repository;
use Kernel243\Artisan\Commands\Resource;
use Kernel243\Artisan\Commands\Service;
use Kernel243\Artisan\Commands\View;

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
                Service::class,
                File::class,
                View::class,
                Resource::class
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
