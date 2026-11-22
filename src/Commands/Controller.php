<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;

class Controller extends BaseCommand
{
    protected $signature = 'kernel:controller {name : The controller class name (e.g. PostController)}
                            {--model= : Generate an API controller based on this model, its repository and its resource}
                            {--module= : The nwidart module where the controller will be created}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create a new controller class (API controller backed by a repository and a resource when --model is given)';

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $target = $this->classTarget($this->argument('name'), 'Http/Controllers', $module);
            $baseController = $this->baseControllerClass($module);
            $model = $this->option('model');

            if (! $model) {
                $content = $this->render($this->getStubContent('empty.controller'), [
                    'DummyNamespace' => $target['namespace'],
                    'DummyClass' => $target['class'],
                    'DummyBaseControllerClass' => $baseController,
                    'DummyBaseController' => $this->classBasename($baseController),
                ]);

                $this->generate($target['path'], $content, 'Controller');

                return self::SUCCESS;
            }

            $modelClass = $this->resolveModel($model, $module);

            if (! $this->modelExists($modelClass)) {
                throw new InvalidArgumentException("The model \"{$modelClass}\" does not exist.");
            }

            $modelName = $this->classBasename($modelClass);
            $repository = $this->classTarget($modelName.'Repository', 'Repositories', $module);
            $resource = $this->classTarget($modelName.'Resource', 'Http/Resources', $module);

            if (! is_file($repository['path'])) {
                $this->call('kernel:repository', array_filter([
                    'name' => $repository['class'],
                    '--model' => $modelClass,
                    '--module' => $module,
                    '--dry-run' => $this->isDryRun(),
                ]));
            }

            if (! is_file($resource['path'])) {
                $this->call('kernel:resource', array_filter([
                    'name' => $resource['class'],
                    '--module' => $module,
                    '--dry-run' => $this->isDryRun(),
                ]));
            }

            $content = $this->render($this->getStubContent('controller'), [
                'DummyNamespace' => $target['namespace'],
                'DummyClass' => $target['class'],
                'DummyBaseControllerClass' => $baseController,
                'DummyBaseController' => $this->classBasename($baseController),
                'DummyModelClass' => $modelClass,
                'DummyModelProperty' => Str::camel($modelName),
                'DummyModel' => $modelName,
                'DummyRepositoryClass' => $repository['namespace'].'\\'.$repository['class'],
                'DummyRepositoryProperty' => Str::camel($repository['class']),
                'DummyRepository' => $repository['class'],
                'DummyResourceClass' => $resource['namespace'].'\\'.$resource['class'],
                'DummyResource' => $resource['class'],
            ]);

            $this->generate($target['path'], $content, 'Controller');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Use the application's base controller when there is one, the framework one otherwise.
     */
    protected function baseControllerClass(?string $module): string
    {
        $appController = $this->rootNamespace().'\\Http\\Controllers\\Controller';

        if (! $module && (class_exists($appController) || is_file($this->laravel->path('Http/Controllers/Controller.php')))) {
            return $appController;
        }

        return 'Illuminate\\Routing\\Controller';
    }
}
