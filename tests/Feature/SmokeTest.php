<?php

use Kernel243\Artisan\Commands\BaseCommand;

it('registers the commands without overriding Laravel native generators', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys([
        'kernel:repository', 'kernel:service', 'kernel:resource', 'kernel:controller', 'kernel:view',
        'kernel:class', 'kernel:file', 'kernel:lang', 'kernel:crud', 'kernel:resource-crud',
        'make:repository', 'make:service', 'make:file', 'make:lang', 'make:crud', 'make:resource-crud',
    ]);

    expect($commands['make:view'])->not->toBeInstanceOf(BaseCommand::class);
    expect($commands['make:class'] ?? null)->not->toBeInstanceOf(BaseCommand::class);
    expect(base_path())->toBe($this->sandboxPath());
});
