<?php

it('creates a blade view extending a layout', function () {
    $this->artisan('kernel:view', ['name' => 'admin.users.index', '--layout' => 'layouts.app'])->assertSuccessful();

    $this->assertValidBlade('resources/views/admin/users/index.blade.php');
    expect(file_get_contents($this->sandboxPath('resources/views/admin/users/index.blade.php')))
        ->toContain("@extends('layouts.app')");
});

it('creates an empty view in a nwidart v10+ module', function () {
    mkdir($this->sandboxPath('Modules/Blog/app'), 0755, true);

    $this->artisan('kernel:view', ['name' => 'posts.index', '--module' => 'Blog'])->assertSuccessful();

    expect($this->sandboxPath('Modules/Blog/resources/views/posts/index.blade.php'))->toBeFile();
});

it('rejects an invalid view name', function () {
    $this->artisan('kernel:view', ['name' => '../oops'])->assertFailed();
});

it('creates a php translation file in lang/', function () {
    $this->artisan('make:lang', ['name' => 'messages', '--locale' => 'fr'])->assertSuccessful();

    $this->assertValidPhp('lang/fr/messages.php');
});

it('creates a json translation file', function () {
    $this->artisan('kernel:lang', ['--locale' => 'es', '--json' => true])->assertSuccessful();

    expect(json_decode(file_get_contents($this->sandboxPath('lang/es.json')), true))->toBe([]);
});

it('requires a name for php translation files', function () {
    $this->artisan('kernel:lang')->assertFailed();
});

it('creates a generic file', function () {
    $this->artisan('make:file', ['filename' => 'docs.api.notes', '--ext' => '.md'])->assertSuccessful();

    expect($this->sandboxPath('docs/api/notes.md'))->toBeFile();
});

it('publishes the stubs and uses the published version', function () {
    $this->artisan('vendor:publish', ['--tag' => 'kernel-stubs'])->assertSuccessful();

    expect($this->sandboxPath('stubs/kernel/repository.stub'))->toBeFile();

    file_put_contents($this->sandboxPath('stubs/kernel/empty.service.stub'), "<?php\n\nnamespace DummyNamespace;\n\nfinal class DummyClass\n{\n}\n");

    $this->artisan('kernel:service', ['name' => 'CustomService'])->assertSuccessful();

    expect(file_get_contents($this->sandboxPath('app/Services/CustomService.php')))->toContain('final class CustomService');
});
