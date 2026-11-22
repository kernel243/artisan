<?php

beforeEach(function () {
    $this->writeFixture('routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
    $this->writeFixture('app/Http/Controllers/Controller.php', "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n}\n");
});

it('generates a complete blade crud', function () {
    $this->artisan('kernel:crud', ['name' => 'BlogPost', '--fields' => 'title:string,body:text,published:boolean,email:email'])
        ->assertSuccessful();

    foreach ([
        'app/Models/BlogPost.php',
        'app/Repositories/BlogPostRepository.php',
        'app/Services/BlogPostService.php',
        'app/Http/Controllers/BlogPostController.php',
        'routes/web.php',
    ] as $file) {
        $this->assertValidPhp($file);
        expect(file_get_contents($this->sandboxPath($file)))->not->toContain('Dummy');
    }

    foreach (['index', 'create', 'edit', 'show'] as $view) {
        $this->assertValidBlade("resources/views/blog-posts/{$view}.blade.php");
        expect(file_get_contents($this->sandboxPath("resources/views/blog-posts/{$view}.blade.php")))->not->toContain('Dummy');
    }

    $this->assertValidBlade('resources/views/layouts/app.blade.php');

    expect(file_get_contents($this->sandboxPath('app/Repositories/BlogPostRepository.php')))
        ->toContain('namespace App\Repositories;')
        ->toContain('use App\Models\BlogPost;');

    expect(file_get_contents($this->sandboxPath('resources/views/blog-posts/index.blade.php')))
        ->toContain("route('blog-posts.create')")
        ->toContain('@forelse($blogPosts as $item)');

    expect(file_get_contents($this->sandboxPath('resources/views/blog-posts/edit.blade.php')))
        ->toContain("old('title', \$blogPost->title)");

    expect(file_get_contents($this->sandboxPath('routes/web.php')))
        ->toContain("Route::resource('blog-posts', \\App\\Http\\Controllers\\BlogPostController::class);")
        ->not->toContain('datatable');

    expect($this->sandboxPath('tailwind.config.js'))->not->toBeFile();
});

it('does not add the routes twice', function () {
    $this->artisan('kernel:crud', ['name' => 'Post'])->assertSuccessful();
    $this->artisan('kernel:crud', ['name' => 'Post', '--force' => true])->assertSuccessful();

    expect(substr_count(file_get_contents($this->sandboxPath('routes/web.php')), "Route::resource('posts'"))->toBe(1);
});

it('keeps the make:crud alias and the opt-in tailwind config', function () {
    $this->artisan('make:crud', ['name' => 'Post', '--tailwind-config' => true])->assertSuccessful();

    expect($this->sandboxPath('tailwind.config.js'))->toBeFile();
});

it('rejects invalid field names', function () {
    $this->artisan('kernel:crud', ['name' => 'Post', '--fields' => 'ti-tle:string'])->assertFailed();
});

it('generates a resource driven crud', function () {
    $this->artisan('kernel:resource-crud', ['name' => 'Product', '--fields' => 'name:string,price:decimal,active:boolean'])
        ->assertSuccessful();

    foreach ([
        'app/Resources/Resource.php',
        'app/Resources/ProductResource.php',
        'app/Builders/Form.php',
        'app/Builders/Table.php',
        'app/Models/Product.php',
        'app/Repositories/ProductRepository.php',
        'app/Services/ProductService.php',
        'app/Http/Controllers/ProductController.php',
    ] as $file) {
        $this->assertValidPhp($file);
        expect(file_get_contents($this->sandboxPath($file)))->not->toContain('Dummy');
    }

    foreach (['_form', 'index', 'create', 'edit', 'show'] as $view) {
        $this->assertValidBlade("resources/views/resources/crud/{$view}.blade.php");
    }

    expect(file_get_contents($this->sandboxPath('app/Resources/ProductResource.php')))
        ->toContain('protected $model = \App\Models\Product::class;')
        ->toContain("Table::number('price', 'Price'),")
        ->toContain("Form::checkbox('active', 'Active'),");
});

it('lets a second resource crud reuse the generic views', function () {
    $this->artisan('kernel:resource-crud', ['name' => 'Product'])->assertSuccessful();
    $this->artisan('make:resource-crud', ['name' => 'Category'])->assertSuccessful();

    $this->assertValidPhp('app/Resources/CategoryResource.php');
});
