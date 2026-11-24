<?php

describe('kernel:tenant', function () {
    beforeEach(function () {
        $this->writeFixture('app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    /**\n     * Register any application services.\n     */\n    public function register(): void\n    {\n        //\n    }\n\n    public function boot(): void\n    {\n        //\n    }\n}\n");
    });

    it('creates the manager, a trait that fails closed and binds the manager', function () {
        $this->makeModel('Organization');
        $this->writeFixture('database/migrations/2026_01_01_000000_create_organizations_table.php', "<?php\n");

        $this->artisan('kernel:tenant')->assertSuccessful();

        $this->assertValidPhp('app/Tenancy/TenantManager.php');
        $this->assertValidPhp('app/Models/Concerns/BelongsToOrganization.php');
        $this->assertValidPhp('app/Providers/AppServiceProvider.php');

        expect(file_get_contents($this->sandboxPath('app/Tenancy/TenantManager.php')))
            ->toContain("return \$user instanceof Model ? \$user->getAttribute('organization_id') : null;")
            ->toContain('public function runFor(Model|int|string $tenant, Closure $callback): mixed');

        expect(file_get_contents($this->sandboxPath('app/Models/Concerns/BelongsToOrganization.php')))
            ->toContain("use App\\Models\\Organization;\nuse App\\Tenancy\\TenantManager;\nuse Illuminate\\Database\\Eloquent\\Builder;")
            ->toContain('public static function bootBelongsToOrganization(): void')
            ->toContain("\$query->whereRaw('1 = 0');")
            ->toContain("\$model->setAttribute('organization_id', app(TenantManager::class)->idOrFail());")
            ->toContain('public function organization(): BelongsTo')
            ->not->toContain('Dummy');

        expect(file_get_contents($this->sandboxPath('app/Providers/AppServiceProvider.php')))
            ->toContain("use App\\Tenancy\\TenantManager;\nuse Illuminate\\Support\\ServiceProvider;")
            ->toContain("    public function register(): void\n    {\n        // Un seul tenant courant par requête, remis à zéro entre deux requêtes (Octane).\n        \$this->app->scoped(TenantManager::class);\n    }");

        $migration = glob($this->sandboxPath('database/migrations/*_add_organization_id_to_users_table.php'))[0];
        expect(file_get_contents($migration))
            ->toContain("\$table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();");
    });

    it('is idempotent', function () {
        $this->writeFixture('database/migrations/2026_01_01_000000_create_churches_table.php', "<?php\n");

        $this->artisan('kernel:tenant', ['model' => 'Church'])->assertSuccessful();
        $this->artisan('kernel:tenant', ['model' => 'Church'])->assertSuccessful();

        expect(substr_count(file_get_contents($this->sandboxPath('app/Providers/AppServiceProvider.php')), 'TenantManager::class'))->toBe(1)
            ->and(glob($this->sandboxPath('database/migrations/*_add_church_id_to_users_table.php')))->toHaveCount(1);
    });

    it('waits for the tenants table before adding the users column', function () {
        $this->artisan('kernel:tenant', ['model' => 'Organization'])
            ->expectsOutputToContain('No migration creates "organizations" yet')
            ->assertSuccessful();

        expect(glob($this->sandboxPath('database/migrations/*_add_organization_id_to_users_table.php')))->toBe([]);
    });

    it('rejects an invalid column', function () {
        $this->artisan('kernel:tenant', ['--column' => 'organization'])->assertFailed();
    });
});

describe('kernel:inertia-crud --tenant', function () {
    beforeEach(function () {
        $this->writeFixture('routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->writeFixture('app/Http/Controllers/Controller.php', "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n}\n");
        $this->writeFixture('app/Models/Author.php', "<?php\n\nnamespace App\\Models;\n\nuse App\\Models\\Concerns\\BelongsToOrganization;\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass Author extends Model\n{\n    use BelongsToOrganization;\n\n    protected \$fillable = ['name'];\n}\n");
    });

    it('isolates the records of each tenant', function () {
        $this->artisan('kernel:inertia-crud', [
            'name' => 'Post',
            '--fields' => 'organization_id:foreignId,title:string,slug:string:unique,author_id:foreignId',
            '--tenant' => 'organization_id',
            '--policy' => true,
        ])->assertSuccessful();

        foreach ([
            'app/Models/Post.php',
            'app/Models/Concerns/BelongsToOrganization.php',
            'app/Tenancy/TenantManager.php',
            'database/factories/PostFactory.php',
            'app/Http/Requests/Posts/StorePostRequest.php',
            'app/Http/Requests/Posts/UpdatePostRequest.php',
            'app/Policies/PostPolicy.php',
            'tests/Feature/PostControllerTest.php',
        ] as $file) {
            $this->assertValidPhp($file);
        }

        $migration = file_get_contents(glob($this->sandboxPath('database/migrations/*_create_posts_table.php'))[0]);
        expect($migration)
            ->toContain("            \$table->id();\n            \$table->foreignId('organization_id')->constrained()->cascadeOnDelete();\n            \$table->string('title');")
            ->toContain("\$table->string('slug');")
            ->toContain("\$table->unique(['organization_id', 'slug']);")
            ->and(substr_count($migration, "'organization_id'"))->toBe(2);

        expect(file_get_contents($this->sandboxPath('app/Models/Post.php')))
            ->toContain('use App\\Models\\Concerns\\BelongsToOrganization;')
            ->toContain("    use BelongsToOrganization;\n\n    /** @use HasFactory<PostFactory> */\n    use HasFactory;")
            ->not->toContain("'organization_id',");

        expect(file_get_contents($this->sandboxPath('database/factories/PostFactory.php')))
            ->toContain("'organization_id' => fn () => app(TenantManager::class)->id() ?? Organization::factory(),")
            ->toContain('use App\\Tenancy\\TenantManager;');

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/UpdatePostRequest.php')))
            ->toContain("Rule::unique('posts', 'slug')->where('organization_id', app(TenantManager::class)->id())->ignore(\$this->route('post'))")
            ->toContain("Rule::exists('authors', 'id')->where('organization_id', app(TenantManager::class)->id())")
            ->toContain('use App\\Tenancy\\TenantManager;')
            ->not->toContain("'organization_id' => [");

        expect(file_get_contents($this->sandboxPath('app/Data/Posts/PostData.php')))
            ->not->toContain('organization_id');

        expect(file_get_contents($this->sandboxPath('app/Policies/PostPolicy.php')))
            ->toContain("return \$this->decide(\$user, \$post, 'posts.update');");

        expect(file_get_contents($this->sandboxPath('tests/Feature/PostControllerTest.php')))
            ->toContain("User::factory()->create(['organization_id' => Organization::factory()->create()->getKey()])->givePermissionTo(\$permissions)")
            ->toContain("it('isole les posts de chaque organization', function () {")
            ->toContain("\$this->get(route('posts.edit', \$other))->assertNotFound();")
            ->not->toContain('Dummy');
    });

    it('keeps non-unique related models unscoped', function () {
        $this->artisan('kernel:inertia-crud', [
            'name' => 'Post',
            '--fields' => 'title:string,category_id:foreignId',
            '--tenant' => 'organization_id',
        ])->assertSuccessful();

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/StorePostRequest.php')))
            ->toContain("Rule::exists('categories', 'id')],")
            ->not->toContain('TenantManager');
    });
});
