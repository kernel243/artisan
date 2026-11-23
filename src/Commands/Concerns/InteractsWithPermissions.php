<?php

namespace Kernel243\Artisan\Commands\Concerns;

use Illuminate\Support\Str;
use Kernel243\Artisan\Commands\BaseCommand;

/**
 * Permission names shared by kernel:policy, kernel:request and kernel:inertia-crud:
 * "<module><separator><ability>", e.g. "blog-posts.update" or "blog-posts:update".
 *
 * @mixin BaseCommand
 */
trait InteractsWithPermissions
{
    protected function permissionModule(string $model): string
    {
        return Str::kebab(Str::plural($model));
    }

    protected function permissionName(string $model, string $ability): string
    {
        return $this->permissionModule($model).$this->permissionSeparator().$ability;
    }

    /**
     * The configured separator, or the one used by an existing PermissionRegistry ("module:ability" or "module.ability").
     */
    protected function permissionSeparator(): string
    {
        $separator = $this->laravel['config']->get('kernel-artisan.permissions.separator');

        if (is_string($separator) && $separator !== '') {
            return $separator;
        }

        $registry = $this->permissionRegistryPath();

        if (is_file($registry) && preg_match('/\}:[a-z{]|[\'"][a-z][a-z-]*:[a-z][a-z-]*[\'"]/', (string) file_get_contents($registry))) {
            return ':';
        }

        return '.';
    }

    protected function permissionRegistryPath(): string
    {
        return $this->laravel->path('Support/PermissionRegistry.php');
    }
}
