<?php

namespace Kernel243\Artisan;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    /**
     * The commands provided by the package.
     *
     * @var list<class-string<Command>>
     */
    public const COMMANDS = [
        Commands\Repository::class,
        Commands\Service::class,
        Commands\Resource::class,
        Commands\Controller::class,
        Commands\View::class,
        Commands\ClassMakeCommand::class,
        Commands\File::class,
        Commands\Lang::class,
        Commands\CrudMakeCommand::class,
        Commands\ResourceMakeCommand::class,
        Commands\ActionMakeCommand::class,
        Commands\DataMakeCommand::class,
        Commands\RequestMakeCommand::class,
        Commands\EnumMakeCommand::class,
        Commands\InertiaCrudMakeCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kernel-artisan.php', 'kernel-artisan');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands(self::COMMANDS);

        $this->publishes([
            __DIR__.'/Commands/stubs' => $this->app->basePath(Commands\BaseCommand::STUBS_OVERRIDE_DIRECTORY),
        ], 'kernel-stubs');

        $this->publishes([
            __DIR__.'/../config/kernel-artisan.php' => $this->app->configPath('kernel-artisan.php'),
        ], 'kernel-config');
    }
}
