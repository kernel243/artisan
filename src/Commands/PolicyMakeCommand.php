<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithPermissions;

/**
 * Policy whose every ability requires a permission, and optionally the same tenant as the user.
 * The permissions are added to App\Support\PermissionRegistry, the single source read by the seeder.
 */
class PolicyMakeCommand extends BaseCommand
{
    use InteractsWithPermissions;

    /**
     * Policy method => permission ability.
     */
    protected const METHODS = [
        'viewAny' => 'view',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
    ];

    protected const ABILITY_LABELS = [
        'view' => 'Voir les',
        'create' => 'Créer des',
        'update' => 'Modifier des',
        'delete' => 'Supprimer des',
    ];

    protected $signature = 'kernel:policy {model : The model the policy protects (e.g. Post)}
                            {--tenant= : Tenant column shared by the user and the record (e.g. organization_id)}
                            {--no-registry : Do not add the permissions to App\Support\PermissionRegistry}
                            {--module= : The nwidart module of the model}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create a policy checking a permission per ability (and the tenant), and register its permissions';

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $modelClass = $this->resolveModel((string) $this->argument('model'), $module);
            $tenant = $this->tenantColumn();
            $target = $this->classTarget($this->classBasename($modelClass).'Policy', 'Policies', $module);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->modelExists($modelClass)) {
            $this->warn("The model \"{$modelClass}\" does not exist yet.");
        }

        if (! class_exists('Spatie\\Permission\\PermissionServiceProvider')) {
            $this->warn('spatie/laravel-permission is not installed: composer require spatie/laravel-permission');
        }

        $this->generate($target['path'], $this->buildPolicy($target, $modelClass, $tenant), 'Policy');

        if (! $this->option('no-registry')) {
            $this->registerPermissions($this->classBasename($modelClass));
        }

        return self::SUCCESS;
    }

    protected function tenantColumn(): ?string
    {
        $tenant = $this->option('tenant');

        if (blank($tenant)) {
            return null;
        }

        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $tenant)) {
            throw new InvalidArgumentException("Invalid tenant column \"{$tenant}\".");
        }

        return $tenant;
    }

    /**
     * @param  array{path: string, namespace: string, class: string}  $target
     */
    protected function buildPolicy(array $target, string $modelClass, ?string $tenant): string
    {
        $model = $this->classBasename($modelClass);
        $variable = Str::camel($model);
        $userClass = (string) $this->laravel['config']->get('auth.providers.users.model', $this->rootNamespace().'\\Models\\User');
        $user = class_basename($userClass);
        $label = Str::lower(Str::headline(Str::plural($model)));

        $methods = self::METHODS;

        if ($this->usesSoftDeletes($modelClass)) {
            $methods += ['restore' => 'update', 'forceDelete' => 'delete'];
        }

        $imports = array_unique([$modelClass, $userClass]);

        if ($tenant !== null) {
            $imports[] = 'Illuminate\\Auth\\Access\\Response';
        }

        sort($imports);

        $bodies = [];

        foreach ($methods as $method => $ability) {
            $permission = $this->permissionName($model, $ability);
            $onRecord = ! in_array($method, ['viewAny', 'create'], true);
            $parameters = $onRecord ? "{$user} \$user, {$model} \${$variable}" : "{$user} \$user";

            $bodies[] = $tenant !== null && $onRecord
                ? "    public function {$method}({$parameters}): Response\n    {\n        return \$this->decide(\$user, \${$variable}, '{$permission}');\n    }"
                : "    public function {$method}({$parameters}): bool\n    {\n        return \$user->can('{$permission}');\n    }";
        }

        if ($tenant !== null) {
            $bodies[] = $this->render($this->getStubContent('policy.tenant'), [
                'DummyUser' => $user,
                'DummyModel' => $model,
                'DummyVariable' => $variable,
                'DummyTenant' => $tenant,
            ]);
        }

        return $this->render($this->getStubContent('policy'), [
            'DummyNamespace' => $target['namespace'],
            'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", $imports)),
            'DummyDescription' => $tenant !== null
                ? "Accès aux {$label} : chaque action demande sa permission ({$this->permissionName($model, 'view')}…)\n * et un enregistrement n'est visible que par les utilisateurs du même {$tenant}."
                : "Accès aux {$label} : chaque action demande sa permission ({$this->permissionName($model, 'view')}…).",
            'DummyClass' => $target['class'],
            'DummyMethods' => implode("\n\n", $bodies),
        ]);
    }

    protected function usesSoftDeletes(string $modelClass): bool
    {
        if (class_exists($modelClass)) {
            return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        }

        $root = $this->rootNamespace().'\\';
        $path = $this->laravel->path(str_replace('\\', '/', Str::after($modelClass, $root)).'.php');

        return is_file($path) && str_contains((string) file_get_contents($path), 'SoftDeletes');
    }

    /**
     * Add the permissions to the registry: the MODULES list ("module:ability" registries), the
     * permission => label map returned by all(), or a new registry and its seeder.
     */
    protected function registerPermissions(string $model): void
    {
        $path = $this->permissionRegistryPath();
        $module = $this->permissionModule($model);
        $label = Str::lower(Str::headline(Str::plural($model)));
        $permissions = [];

        foreach (self::ABILITY_LABELS as $ability => $prefix) {
            $permissions[$this->permissionName($model, $ability)] = "{$prefix} {$label}";
        }

        if (! is_file($path)) {
            $this->createRegistry($permissions);

            return;
        }

        $registry = (string) file_get_contents($path);
        $updated = null;

        if (preg_match('/const MODULES = \[\n(.*?)\n(\s*)\];/s', $registry, $matches, PREG_OFFSET_CAPTURE)) {
            if (preg_match('/[\'"]'.preg_quote($module, '/').'[\'"]/', $matches[1][0])) {
                $this->line("Permissions of \"{$module}\" already registered: skipped.");

                return;
            }

            $indent = $matches[2][0].'    ';
            $end = $matches[1][1] + strlen($matches[1][0]);
            $updated = substr($registry, 0, $end)."\n{$indent}'{$module}',".substr($registry, $end);
        } elseif (preg_match('/function all\(\): array\s*\{.*?return \[\n(.*?)\n(\s*)\];/s', $registry, $matches, PREG_OFFSET_CAPTURE)) {
            $missing = array_filter($permissions, fn (string $name) => ! str_contains($matches[1][0], "'{$name}'"), ARRAY_FILTER_USE_KEY);

            if ($missing === []) {
                $this->line("Permissions of \"{$module}\" already registered: skipped.");

                return;
            }

            $indent = $matches[2][0].'    ';
            $lines = array_map(fn (string $name, string $text) => "{$indent}'{$name}' => '".addslashes($text)."',", array_keys($missing), $missing);
            $end = $matches[1][1] + strlen($matches[1][0]);
            $updated = substr($registry, 0, $end)."\n".implode("\n", $lines).substr($registry, $end);
        }

        if ($updated === null) {
            $this->warn('The layout of '.$this->relativePath($path).' is not recognized: add these permissions by hand:');
            $this->line(implode("\n", array_keys($permissions)));

            return;
        }

        if ($this->isDryRun()) {
            $this->line('<comment>[dry-run]</comment> Permissions would be added to '.$this->relativePath($path).': '.implode(', ', array_keys($permissions)));

            return;
        }

        file_put_contents($path, $updated);
        $this->info('Permissions added to '.$this->relativePath($path));
    }

    /**
     * @param  array<string, string>  $permissions
     */
    protected function createRegistry(array $permissions): void
    {
        $lines = array_map(
            fn (string $name, string $label) => "            '{$name}' => '".addslashes($label)."',",
            array_keys($permissions),
            $permissions,
        );

        $this->generate($this->permissionRegistryPath(), $this->render($this->getStubContent('permission-registry'), [
            'DummyNamespace' => $this->rootNamespace().'\\Support',
            'DummyPermissions' => implode("\n", $lines),
        ]), 'Permission registry');

        $seeder = $this->laravel->databasePath('seeders/RolesAndPermissionsSeeder.php');

        if (! is_file($seeder)) {
            $this->generate($seeder, $this->render($this->getStubContent('permissions-seeder'), [
                'DummyRegistryClass' => $this->rootNamespace().'\\Support\\PermissionRegistry',
            ]), 'Seeder');
        }
    }
}
