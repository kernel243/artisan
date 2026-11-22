<?php

namespace Kernel243\Artisan\Commands;

use InvalidArgumentException;

class Resource extends BaseCommand
{
    protected $signature = 'kernel:resource {name : The API resource class name (e.g. UserResource)}
                            {--module= : The nwidart module where the resource will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new API resource class (optionally inside a module)';

    public function handle(): int
    {
        try {
            $target = $this->classTarget($this->argument('name'), 'Http/Resources', $this->option('module') ?: null);

            $content = $this->render($this->getStubContent('empty.resource'), [
                'DummyNamespace' => $target['namespace'],
                'DummyClass' => $target['class'],
            ]);

            $this->generate($target['path'], $content, 'Resource');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
