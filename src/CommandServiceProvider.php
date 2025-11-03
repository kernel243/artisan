<?php

namespace Kernel243\Artisan;

use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\Repository::class,
                Commands\Service::class,
                Commands\File::class,
                Commands\View::class,
                Commands\Resource::class,
                Commands\Controller::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }
}
