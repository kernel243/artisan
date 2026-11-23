<?php

use Illuminate\Support\Facades\Config;

function inertiaCrud(array $options = []): array
{
    return ['name' => 'Post', '--fields' => 'title:string,email:email:unique,body:text?,price:decimal,is_published:boolean,published_at:datetime?,author_id:foreignId,password:password', ...$options];
}

describe('kernel:inertia-crud', function () {
    beforeEach(function () {
        $this->writeFixture('routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->writeFixture('app/Http/Controllers/Controller.php', "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n}\n");
    });

    it('generates the whole back end', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud())->assertSuccessful();

        foreach ([
            'app/Models/Post.php',
            'database/factories/PostFactory.php',
            'app/Data/Posts/PostData.php',
            'app/Http/Requests/Posts/StorePostRequest.php',
            'app/Http/Requests/Posts/UpdatePostRequest.php',
            'app/Actions/Posts/CreatePostAction.php',
            'app/Actions/Posts/UpdatePostAction.php',
            'app/Actions/Posts/DeletePostAction.php',
            'app/Http/Controllers/PostController.php',
            'tests/Feature/PostControllerTest.php',
        ] as $file) {
            $this->assertValidPhp($file);
        }

        $migration = glob($this->sandboxPath('database/migrations/*_create_posts_table.php'))[0];
        exec('php -l '.escapeshellarg($migration), $output, $code);
        expect($code)->toBe(0)
            ->and(file_get_contents($migration))
            ->toContain("\$table->string('email')->unique();")
            ->toContain("\$table->text('body')->nullable();")
            ->toContain("\$table->decimal('price', 15, 2);")
            ->toContain("\$table->foreignId('author_id')->constrained()->cascadeOnDelete();")
            ->toContain('$table->softDeletes();');

        expect(file_get_contents($this->sandboxPath('app/Models/Post.php')))
            ->toContain('use SoftDeletes;')
            ->toContain('/** @use HasFactory<PostFactory> */')
            ->toContain("'price' => 'decimal:2',")
            ->toContain("'password' => 'hashed',")
            ->toContain('protected $hidden = [');

        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/PostController.php')))
            ->toContain("return Inertia::render('posts/Index', [")
            ->toContain("'title' => \$post->title,")
            ->toContain("'published_at' => \$post->published_at?->format('Y-m-d\\TH:i'),")
            ->not->toContain("'password' =>")
            ->toContain('public function store(StorePostRequest $request, CreatePostAction $action): RedirectResponse')
            ->toContain('$action->handle(PostData::from($request->validated()));')
            ->toContain("return to_route('posts.index')->with('success', 'Enregistrement créé avec succès.');");

        expect(file_get_contents($this->sandboxPath('routes/web.php')))
            ->toContain("use App\\Http\\Controllers\\PostController;\nuse Illuminate\\Support\\Facades\\Route;")
            ->toContain("Route::resource('posts', PostController::class)->except('show');");
    });

    it('generates the Vue pages', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud())->assertSuccessful();

        expect(file_get_contents($this->sandboxPath('resources/js/pages/posts/Index.vue')))
            ->toContain("import postRoutes from '@/routes/posts';")
            ->toContain('posts: Paginated<PostRow>;')
            ->toContain('{{ post.is_published ? \'Oui\' : \'Non\' }}')
            ->not->toContain('password')
            ->not->toContain('Dummy');

        expect(file_get_contents($this->sandboxPath('resources/js/pages/posts/Form.vue')))
            ->toContain('form.submit(postRoutes.update(props.post.id));')
            ->toContain('<Input id="title" v-model="form.title" type="text" />')
            ->toContain("<Input\n                    id=\"password\"\n                    v-model=\"form.password\"\n                    type=\"password\"\n                    autocomplete=\"new-password\"\n                />")
            ->toContain("price: props.post?.price ?? ('' as number | string),")
            ->toContain('is_published: props.post?.is_published ?? false,')
            ->not->toContain('Dummy');
    });

    it('generates a zone', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud(['name' => 'BlogPost', '--zone' => 'admin']))->assertSuccessful();

        $this->assertValidPhp('app/Http/Controllers/Admin/BlogPostController.php');
        $this->assertValidPhp('tests/Feature/Admin/BlogPostControllerTest.php');

        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/Admin/BlogPostController.php')))
            ->toContain('namespace App\Http\Controllers\Admin;')
            ->toContain('use App\Http\Controllers\Controller;')
            ->toContain("Inertia::render('admin/blog-posts/Form'")
            ->toContain("to_route('admin.blog-posts.index')");

        expect(file_get_contents($this->sandboxPath('routes/web.php')))
            ->toContain("Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {");

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/BlogPosts/UpdateBlogPostRequest.php')))
            ->toContain("->ignore(\$this->route('blog_post'))");

        expect($this->sandboxPath('resources/js/pages/admin/blog-posts/Index.vue'))->toBeFile()
            ->and(file_get_contents($this->sandboxPath('resources/js/pages/admin/blog-posts/Index.vue')))
            ->toContain("import blogPostRoutes from '@/routes/admin/blog-posts';");
    });

    it('adds the routes to routes/<zone>.php when it exists', function () {
        $this->writeFixture('routes/admin.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        $this->artisan('kernel:inertia-crud', inertiaCrud(['--zone' => 'admin']))->assertSuccessful();

        expect(file_get_contents($this->sandboxPath('routes/admin.php')))
            ->toContain('use App\\Http\\Controllers\\Admin\\PostController;')
            ->toContain("Route::resource('posts', PostController::class)->except('show');")
            ->not->toContain('->group(');
    });

    it('follows the project conventions', function () {
        Config::set('kernel-artisan.actions', ['suffix' => '', 'method' => '__invoke', 'transaction' => true]);
        Config::set('kernel-artisan.inertia.flash', 'toast');
        Config::set('kernel-artisan.inertia.layout', 'component');
        mkdir($this->sandboxPath('resources/js/Pages'), 0777, true);

        $this->artisan('kernel:inertia-crud', inertiaCrud(['--zone' => 'admin']))->assertSuccessful();

        $this->assertValidPhp('app/Http/Controllers/Admin/PostController.php');
        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/Admin/PostController.php')))
            ->toContain('CreatePost $action')
            ->toContain('$action(PostData::from($request->validated()));')
            ->toContain("->with('toast', ['type' => 'success', 'message' => 'Enregistrement créé avec succès.'])")
            ->toContain("Inertia::render('Admin/Posts/Index'");

        expect(file_get_contents($this->sandboxPath('resources/js/Pages/Admin/Posts/Index.vue')))
            ->toContain('const breadcrumbs: BreadcrumbItem[] = [')
            ->toContain('<AppLayout :breadcrumbs="breadcrumbs">')
            ->not->toContain('defineOptions');
    });

    it('does not add the routes twice and avoids short name clashes', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud())->assertSuccessful();
        $this->artisan('kernel:inertia-crud', inertiaCrud(['--force' => true]))->assertSuccessful();
        $this->artisan('kernel:inertia-crud', inertiaCrud(['--zone' => 'admin', '--force' => true]))->assertSuccessful();

        $routes = file_get_contents($this->sandboxPath('routes/web.php'));

        expect(substr_count($routes, "Route::resource('posts', PostController::class)"))->toBe(1)
            ->and($routes)->toContain("Route::resource('posts', \\App\\Http\\Controllers\\Admin\\PostController::class)");
    });

    it('omits the search without a text column', function () {
        $this->artisan('kernel:inertia-crud', ['name' => 'Counter', '--fields' => 'value:integer'])->assertSuccessful();

        $this->assertValidPhp('app/Http/Controllers/CounterController.php');
        expect(file_get_contents($this->sandboxPath('resources/js/pages/counters/Index.vue')))
            ->not->toContain('watchDebounced')
            ->not->toContain('Rechercher');
        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/CounterController.php')))
            ->not->toContain('->when(');
    });

    it('writes nothing in dry-run', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud(['--dry-run' => true]))->assertSuccessful();

        expect($this->sandboxPath('app/Models/Post.php'))->not->toBeFile()
            ->and($this->sandboxPath('app/Http/Controllers/PostController.php'))->not->toBeFile()
            ->and($this->sandboxPath('resources/js/pages/posts/Index.vue'))->not->toBeFile()
            ->and(file_get_contents($this->sandboxPath('routes/web.php')))->not->toContain('PostController');
    });
});
