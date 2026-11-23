<?php

use Illuminate\Support\Facades\Config;

describe('kernel:policy', function () {
    it('checks a permission per ability and creates the registry and the seeder', function () {
        $this->makeModel('BlogPost');

        $this->artisan('kernel:policy', ['model' => 'BlogPost'])->assertSuccessful();

        $this->assertValidPhp('app/Policies/BlogPostPolicy.php');
        $this->assertValidPhp('app/Support/PermissionRegistry.php');
        $this->assertValidPhp('database/seeders/RolesAndPermissionsSeeder.php');

        expect(file_get_contents($this->sandboxPath('app/Policies/BlogPostPolicy.php')))
            ->toContain('public function viewAny(User $user): bool')
            ->toContain("return \$user->can('blog-posts.view');")
            ->toContain('public function update(User $user, BlogPost $blogPost): bool')
            ->toContain("return \$user->can('blog-posts.update');")
            ->not->toContain('forceDelete');

        expect(file_get_contents($this->sandboxPath('app/Support/PermissionRegistry.php')))
            ->toContain("'blog-posts.create' => 'Créer des blog posts',");
    });

    it('denies records of another tenant as not found', function () {
        $this->writeFixture('app/Models/Post.php', "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;\n\nclass Post extends Model\n{\n    use SoftDeletes;\n}\n");

        $this->artisan('kernel:policy', ['model' => 'Post', '--tenant' => 'organization_id'])->assertSuccessful();

        $this->assertValidPhp('app/Policies/PostPolicy.php');
        expect(file_get_contents($this->sandboxPath('app/Policies/PostPolicy.php')))
            ->toContain('use Illuminate\Auth\Access\Response;')
            ->toContain('public function view(User $user, Post $post): Response')
            ->toContain("return \$this->decide(\$user, \$post, 'posts.view');")
            ->toContain('public function create(User $user): bool')
            ->toContain('public function forceDelete(User $user, Post $post): Response')
            ->toContain('$user->organization_id === null || $post->organization_id !== $user->organization_id')
            ->toContain('return Response::denyAsNotFound();');
    });

    it('adds the permissions to an all() registry', function () {
        $this->makeModel('Post');
        $this->writeFixture('app/Support/PermissionRegistry.php', "<?php\n\nnamespace App\\Support;\n\nfinal class PermissionRegistry\n{\n    public static function all(): array\n    {\n        return [\n            'users.manage' => 'Gérer les comptes',\n        ];\n    }\n}\n");

        $this->artisan('kernel:policy', ['model' => 'Post'])->assertSuccessful();
        $this->artisan('kernel:policy', ['model' => 'Post', '--force' => true])->assertSuccessful();

        $this->assertValidPhp('app/Support/PermissionRegistry.php');
        $registry = file_get_contents($this->sandboxPath('app/Support/PermissionRegistry.php'));

        expect($registry)
            ->toContain("            'users.manage' => 'Gérer les comptes',\n            'posts.view' => 'Voir les posts',")
            ->and(substr_count($registry, "'posts.delete'"))->toBe(1)
            ->and($this->sandboxPath('database/seeders/RolesAndPermissionsSeeder.php'))->not->toBeFile();
    });

    it('adds the module to a MODULES registry and follows its separator', function () {
        $this->makeModel('Post');
        $this->writeFixture('app/Support/PermissionRegistry.php', "<?php\n\nnamespace App\\Support;\n\nfinal class PermissionRegistry\n{\n    public const MODULES = [\n        'members',\n        'families',\n    ];\n\n    public static function permissions(): array\n    {\n        return array_map(fn (\$module) => \"{\$module}:view\", self::MODULES);\n    }\n}\n");

        $this->artisan('kernel:policy', ['model' => 'Post'])->assertSuccessful();

        $this->assertValidPhp('app/Support/PermissionRegistry.php');
        expect(file_get_contents($this->sandboxPath('app/Support/PermissionRegistry.php')))
            ->toContain("        'families',\n        'posts',\n    ];");
        expect(file_get_contents($this->sandboxPath('app/Policies/PostPolicy.php')))
            ->toContain("\$user->can('posts:update')");
    });

    it('uses the configured separator in the form requests too', function () {
        Config::set('kernel-artisan.permissions.separator', ':');
        Config::set('kernel-artisan.requests.authorize_in_controller', false);

        $this->artisan('kernel:request', ['model' => 'Post', '--fields' => 'title:string'])->assertSuccessful();

        expect(file_get_contents($this->sandboxPath('app/Http/Requests/Posts/StorePostRequest.php')))
            ->toContain("\$this->user()?->can('posts:create')");
    });
});
