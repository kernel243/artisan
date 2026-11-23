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
            ->not->toContain('can.')
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

    it('authorizes every action with --policy', function () {
        $this->artisan('kernel:inertia-crud', inertiaCrud(['--policy' => true]))->assertSuccessful();

        $this->assertValidPhp('app/Policies/PostPolicy.php');
        $this->assertValidPhp('app/Support/PermissionRegistry.php');
        $this->assertValidPhp('tests/Feature/PostControllerTest.php');

        expect(file_get_contents($this->sandboxPath('app/Policies/PostPolicy.php')))
            ->toContain('public function forceDelete(User $user, Post $post): bool');

        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/PostController.php')))
            ->toContain('use Illuminate\Support\Facades\Gate;')
            ->toContain("Gate::authorize('viewAny', Post::class);")
            ->toContain("Gate::authorize('update', \$post);")
            ->toContain("Gate::authorize('delete', \$post);")
            ->toContain("'update' => \$request->user()->can('update', \$post),")
            ->toContain("'can' => ['create' => \$request->user()->can('create', Post::class)],");

        expect(file_get_contents($this->sandboxPath('resources/js/pages/posts/Index.vue')))
            ->toContain('<Button v-if="can.create" as-child>')
            ->toContain('v-if="post.can.update"')
            ->toContain('can: { update: boolean; delete: boolean };');

        expect(file_get_contents($this->sandboxPath('tests/Feature/PostControllerTest.php')))
            ->toContain('use Spatie\Permission\Models\Permission;')
            ->toContain("\$permissions = ['posts.view', 'posts.create', 'posts.update', 'posts.delete'];")
            ->toContain("it('refuse la liste sans permission'");
    });

    it('edits foreign keys and enums through selects', function () {
        $this->writeFixture('app/Enums/PostStatus.php', "<?php\n\nnamespace App\\Enums;\n\nuse App\\Enums\\Concerns\\HasOptions;\n\nenum PostStatus: string\n{\n    use HasOptions;\n\n    case Draft = 'draft';\n\n    public function label(): string\n    {\n        return 'Brouillon';\n    }\n}\n");
        $this->writeFixture('app/Models/Writer.php', "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass Writer extends Model\n{\n    protected \$fillable = ['email', 'full_name'];\n}\n");
        require_once $this->sandboxPath('app/Models/Writer.php');

        $this->artisan('kernel:inertia-crud', ['name' => 'Post', '--fields' => 'title:string,status:enum,writer_id:foreignId,editor_id:foreignId?'])->assertSuccessful();

        foreach (['app/Models/Post.php', 'app/Http/Controllers/PostController.php', 'app/Http/Requests/Posts/StorePostRequest.php', 'app/Data/Posts/PostData.php', 'database/factories/PostFactory.php'] as $file) {
            $this->assertValidPhp($file);
        }

        expect(file_get_contents($this->sandboxPath('app/Models/Post.php')))
            ->toContain('use App\Enums\PostStatus;')
            ->not->toContain('use App\Models\Writer;')
            ->toContain("'status' => PostStatus::class,")
            ->toContain("     * @return BelongsTo<Writer, \$this>\n     */\n    public function writer(): BelongsTo")
            ->toContain('return $this->belongsTo(Editor::class);');

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/StorePostRequest.php')))
            ->toContain('use App\Enums\PostStatus;')
            ->toContain("'status' => ['required', Rule::enum(PostStatus::class)],");

        expect(file_get_contents($this->sandboxPath('app/Data/Posts/PostData.php')))
            ->toContain('public PostStatus $status,');

        expect(file_get_contents($this->sandboxPath('database/factories/PostFactory.php')))
            ->toContain("'status' => fake()->randomElement(PostStatus::cases()),");

        expect(file_get_contents($this->sandboxPath('app/Http/Controllers/PostController.php')))
            ->toContain("->with(['writer:id,full_name'])")
            ->toContain("'status' => \$post->status?->label(),")
            ->toContain("'writer' => \$post->writer?->full_name,")
            ->toContain("'editor_id' => \$post->editor_id,")
            ->toContain("return Inertia::render('posts/Form', \$this->formOptions());")
            ->toContain('...$this->formOptions(),')
            ->toContain("'statusOptions' => PostStatus::options(),")
            ->toContain("->map(fn (Writer \$writer): array => ['value' => \$writer->id, 'label' => \$writer->full_name]),")
            ->toContain("->map(fn (Editor \$editor): array => ['value' => \$editor->id, 'label' => (string) \$editor->id]),");

        expect(file_get_contents($this->sandboxPath('resources/js/pages/posts/Form.vue')))
            ->toContain('type Option = { value: number | string; label: string };')
            ->toContain('writerOptions: Option[];')
            ->toContain('v-for="option in statusOptions"')
            ->toContain('<option value="" disabled>Choisir…</option>')
            ->toContain('<option value="">Aucun</option>');

        expect(file_get_contents($this->sandboxPath('resources/js/pages/posts/Index.vue')))
            ->toContain('writer: string | null;')
            ->toContain("{{ post.writer ?? '—' }}");
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
