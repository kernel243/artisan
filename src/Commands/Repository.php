<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;

class Repository extends BaseCommand
{
    protected $signature = 'kernel:repository {name : The repository class name (e.g. UserRepository or Admin/UserRepository)}
                            {--model= : The model the repository is based on}
                            {--module= : The nwidart module where the repository will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new repository class';

    protected $aliases = ['make:repository'];

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $target = $this->classTarget($this->argument('name'), 'Repositories', $module);
            $model = $this->option('model');

            if (! $model) {
                $content = $this->render($this->getStubContent('empty.repository'), [
                    'DummyNamespace' => $target['namespace'],
                    'DummyClass' => $target['class'],
                ]);
            } else {
                $modelClass = $this->resolveModel($model, $module);

                if (! $this->modelExists($modelClass)) {
                    throw new InvalidArgumentException("The model \"{$modelClass}\" does not exist.");
                }

                $modelName = $this->classBasename($modelClass);
                $content = $this->render($this->getStubContent('repository'), [
                    'DummyNamespace' => $target['namespace'],
                    'DummyClass' => $target['class'],
                    'DummyModelClass' => $modelClass,
                    'DummyModel' => $modelName,
                    'DummyProperty' => Str::camel($modelName),
                ]);
            }

            $this->generate($target['path'], $content, 'Repository');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
