<?php

namespace Kernel243\Artisan\Commands;

use InvalidArgumentException;

class Service extends BaseCommand
{
    protected $signature = 'kernel:service {name : The service class name (e.g. PayPalPaymentService)}
                            {--module= : The nwidart module where the service will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new service class';

    protected $aliases = ['make:service'];

    public function handle(): int
    {
        try {
            $target = $this->classTarget($this->argument('name'), 'Services', $this->option('module') ?: null);

            $content = $this->render($this->getStubContent('empty.service'), [
                'DummyNamespace' => $target['namespace'],
                'DummyClass' => $target['class'],
            ]);

            $this->generate($target['path'], $content, 'Service');

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
