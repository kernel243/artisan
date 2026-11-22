<?php

namespace Kernel243\Artisan\Commands\Concerns;

use Illuminate\Support\Str;
use Kernel243\Artisan\Commands\BaseCommand;

/**
 * Shared generation steps of kernel:crud and kernel:resource-crud.
 *
 * @mixin BaseCommand
 */
trait GeneratesCrud
{
    use InteractsWithFields;

    /**
     * @return array{model: string, variable: string, plural: string, kebab: string, table: string}
     */
    protected function crudNames(string $name): array
    {
        [, $model] = $this->parseName($name);

        return [
            'model' => $model,
            'variable' => Str::camel($model),
            'plural' => Str::plural(Str::camel($model)),
            'kebab' => Str::kebab(Str::plural($model)),
            'table' => Str::snake(Str::plural($model)),
        ];
    }

    protected function modelClass(string $model): string
    {
        return $this->rootNamespace().'\\Models\\'.$model;
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function generateModel(string $model, array $fields): void
    {
        $fillable = $fields === []
            ? "// 'field1', 'field2',"
            : implode("\n        ", array_map(fn (array $field) => "'{$field['name']}',", $fields));

        $this->generate($this->laravel->path("Models/{$model}.php"), $this->render($this->getStubContent('crud.model'), [
            'DummyNamespace' => $this->rootNamespace().'\\Models',
            'DummyModel' => $model,
            'DummyTable' => Str::snake(Str::plural($model)),
            'DummyFillable' => $fillable,
        ]), 'Model');
    }

    protected function generateRepository(string $model): void
    {
        $this->generate($this->laravel->path("Repositories/{$model}Repository.php"), $this->render($this->getStubContent('repository'), [
            'DummyNamespace' => $this->rootNamespace().'\\Repositories',
            'DummyClass' => $model.'Repository',
            'DummyModelClass' => $this->modelClass($model),
            'DummyModel' => $model,
            'DummyProperty' => Str::camel($model),
        ]), 'Repository');
    }

    protected function generateService(string $model): void
    {
        $this->generate($this->laravel->path("Services/{$model}Service.php"), $this->render($this->getStubContent('crud.service'), [
            'DummyNamespace' => $this->rootNamespace().'\\Services',
            'DummyClass' => $model.'Service',
            'DummyRepositoryClass' => $this->rootNamespace().'\\Repositories\\'.$model.'Repository',
            'DummyRepository' => $model.'Repository',
            'DummyModel' => $model,
        ]), 'Service');
    }

    /**
     * Append a Route::resource() line to routes/web.php when it is not there yet.
     */
    protected function generateRoutes(string $model, string $routePrefix): void
    {
        $routesPath = $this->laravel->basePath('routes/web.php');

        if (! is_file($routesPath)) {
            $this->warn('routes/web.php not found: routes not added.');

            return;
        }

        $routes = (string) file_get_contents($routesPath);

        if (str_contains($routes, "Route::resource('{$routePrefix}'")) {
            $this->line("Routes for \"{$routePrefix}\" already exist in routes/web.php: skipped.");

            return;
        }

        $stub = $this->render($this->getStubContent('crud.routes'), [
            'DummyResourceName' => Str::plural($model),
            'DummyRoutePrefix' => $routePrefix,
            'DummyControllerClass' => '\\'.$this->rootNamespace().'\\Http\\Controllers\\'.$model.'Controller',
        ]);

        if ($this->isDryRun()) {
            $this->line('<comment>[dry-run]</comment> Routes would be added to routes/web.php:');
            $this->line($stub);

            return;
        }

        file_put_contents($routesPath, rtrim($routes)."\n\n".$stub);
        $this->info('Routes added to routes/web.php');
    }

    /**
     * Create a minimal layout only when the application has none.
     */
    protected function generateLayout(): void
    {
        $path = $this->laravel->resourcePath('views/layouts/app.blade.php');

        if (! is_file($path)) {
            $this->generate($path, $this->getStubContent('crud.layout'), 'Layout');
        }
    }

    /**
     * Tailwind CSS v4 no longer uses tailwind.config.js: only create it on demand.
     */
    protected function generateTailwindConfig(): void
    {
        $path = $this->laravel->basePath('tailwind.config.js');

        if ($this->option('tailwind-config') && ! is_file($path)) {
            $this->generate($path, $this->getStubContent('tailwind.config'), 'Tailwind config');
        }
    }
}
