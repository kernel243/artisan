<?php

it('creates an empty repository', function () {
    $this->artisan('kernel:repository', ['name' => 'UserRepository'])->assertSuccessful();

    $this->assertValidPhp('app/Repositories/UserRepository.php');
    expect(file_get_contents($this->sandboxPath('app/Repositories/UserRepository.php')))
        ->toContain('namespace App\Repositories;')
        ->toContain('class UserRepository');
});

it('keeps the make:repository alias', function () {
    $this->artisan('make:repository', ['name' => 'Admin/UserRepository'])->assertSuccessful();

    expect(file_get_contents($this->sandboxPath('app/Repositories/Admin/UserRepository.php')))
        ->toContain('namespace App\Repositories\Admin;');
});

it('creates a repository based on a model', function () {
    $this->makeModel('Post');

    $this->artisan('kernel:repository', ['name' => 'PostRepository', '--model' => 'Post'])->assertSuccessful();

    $this->assertValidPhp('app/Repositories/PostRepository.php');
    expect(file_get_contents($this->sandboxPath('app/Repositories/PostRepository.php')))
        ->toContain('use App\Models\Post;')
        ->toContain('protected Post $post = new Post()')
        ->not->toContain('Dummy');
});

it('fails when the model does not exist', function () {
    $this->artisan('kernel:repository', ['name' => 'PostRepository', '--model' => 'Post'])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    expect($this->sandboxPath('app/Repositories/PostRepository.php'))->not->toBeFile();
});

it('creates a repository in a legacy nwidart module (Entities)', function () {
    $this->makeModel('Country', 'Modules/Setting/Entities', 'Modules\\Setting\\Entities');

    $this->artisan('kernel:repository', ['name' => 'CountryRepository', '--model' => 'Country', '--module' => 'Setting'])
        ->assertSuccessful();

    $this->assertValidPhp('Modules/Setting/Repositories/CountryRepository.php');
    expect(file_get_contents($this->sandboxPath('Modules/Setting/Repositories/CountryRepository.php')))
        ->toContain('namespace Modules\Setting\Repositories;')
        ->toContain('use Modules\Setting\Entities\Country;');
});

it('creates a repository in a nwidart v10+ module (app/Models)', function () {
    $this->makeModel('Country', 'Modules/Setting/app/Models', 'Modules\\Setting\\Models');

    $this->artisan('kernel:repository', ['name' => 'CountryRepository', '--model' => 'Country', '--module' => 'setting'])
        ->assertSuccessful();

    $this->assertValidPhp('Modules/Setting/app/Repositories/CountryRepository.php');
    expect(file_get_contents($this->sandboxPath('Modules/Setting/app/Repositories/CountryRepository.php')))
        ->toContain('use Modules\Setting\Models\Country;');
});

it('fails when the module does not exist', function () {
    $this->artisan('kernel:service', ['name' => 'PaymentService', '--module' => 'Billing'])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('creates a service', function () {
    $this->artisan('make:service', ['name' => 'PayPalPaymentService'])->assertSuccessful();

    $this->assertValidPhp('app/Services/PayPalPaymentService.php');
});

it('creates an api resource in a module', function () {
    mkdir($this->sandboxPath('Modules/Blog/app'), 0755, true);

    $this->artisan('kernel:resource', ['name' => 'PostResource', '--module' => 'Blog'])->assertSuccessful();

    $this->assertValidPhp('Modules/Blog/app/Http/Resources/PostResource.php');
    expect(file_get_contents($this->sandboxPath('Modules/Blog/app/Http/Resources/PostResource.php')))
        ->toContain('namespace Modules\Blog\Http\Resources;');
});

it('creates an empty controller extending the application controller', function () {
    $this->writeFixture('app/Http/Controllers/Controller.php', "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n}\n");

    $this->artisan('kernel:controller', ['name' => 'HomeController'])->assertSuccessful();

    $this->assertValidPhp('app/Http/Controllers/HomeController.php');
    expect(file_get_contents($this->sandboxPath('app/Http/Controllers/HomeController.php')))
        ->toContain('use App\Http\Controllers\Controller;')
        ->toContain('class HomeController extends Controller');
});

it('creates an api controller with its repository and resource', function () {
    $this->makeModel('Post');

    $this->artisan('kernel:controller', ['name' => 'PostController', '--model' => 'Post'])->assertSuccessful();

    foreach (['Http/Controllers/PostController', 'Repositories/PostRepository', 'Http/Resources/PostResource'] as $file) {
        $this->assertValidPhp("app/{$file}.php");
    }

    expect(file_get_contents($this->sandboxPath('app/Http/Controllers/PostController.php')))
        ->toContain('use App\Repositories\PostRepository;')
        ->toContain('use App\Http\Resources\PostResource;')
        ->toContain('protected PostRepository $postRepository')
        ->toContain('public function show(Post $post): PostResource')
        ->toContain('extends Controller')
        ->not->toContain('Dummy')
        ->not->toContain('countryRepository');
});

it('does not overwrite an existing file without --force', function () {
    $this->writeFixture('app/Services/BillingService.php', 'original');

    $this->artisan('kernel:service', ['name' => 'BillingService'])
        ->expectsConfirmation('Service already exists (app/Services/BillingService.php). Replace it?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->sandboxPath('app/Services/BillingService.php')))->toBe('original');

    $this->artisan('kernel:service', ['name' => 'BillingService', '--force' => true])->assertSuccessful();

    expect(file_get_contents($this->sandboxPath('app/Services/BillingService.php')))->toContain('class BillingService');
});

it('writes nothing in dry-run mode', function () {
    $this->artisan('kernel:service', ['name' => 'DryService', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertSuccessful();

    expect($this->sandboxPath('app/Services/DryService.php'))->not->toBeFile();
});

it('creates classes, traits, interfaces and enums', function (string $kind, string $keyword) {
    $this->artisan('kernel:class', ['name' => 'App/Support/Thing', '--kind' => $kind])->assertSuccessful();

    $this->assertValidPhp('app/Support/Thing.php');
    expect(file_get_contents($this->sandboxPath('app/Support/Thing.php')))
        ->toContain('namespace App\Support;')
        ->toContain("{$keyword} Thing");
})->with([
    ['class', 'class'],
    ['trait', 'trait'],
    ['interface', 'interface'],
    ['enum', 'enum'],
]);

it('supports a custom separator and namespaces outside app/', function () {
    $this->artisan('kernel:class', ['name' => 'Domain.Billing.Invoice', '--separator' => '.'])->assertSuccessful();

    $this->assertValidPhp('Domain/Billing/Invoice.php');
});

it('rejects an unknown kind', function () {
    $this->artisan('kernel:class', ['name' => 'App/Thing', '--kind' => 'struct'])->assertFailed();
});
