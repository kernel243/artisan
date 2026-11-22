<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

abstract class BaseCommand extends Command
{
    /**
     * Directory (relative to the application base path) where published stubs live.
     */
    public const STUBS_OVERRIDE_DIRECTORY = 'stubs/kernel';

    protected function files(): Filesystem
    {
        return $this->laravel->make(Filesystem::class);
    }

    /**
     * Get the content of a stub, preferring a published override in stubs/kernel.
     */
    protected function getStubContent(string $name): string
    {
        $override = $this->laravel->basePath(self::STUBS_OVERRIDE_DIRECTORY.'/'.$name.'.stub');
        $path = is_file($override) ? $override : __DIR__.'/stubs/'.$name.'.stub';

        if (! is_file($path)) {
            throw new RuntimeException("Stub not found: {$name}");
        }

        return (string) file_get_contents($path);
    }

    /**
     * Replace placeholders in a stub (longest placeholders are replaced first).
     *
     * @param  array<string, string>  $replacements
     */
    protected function render(string $stub, array $replacements): string
    {
        return strtr($stub, $replacements);
    }

    /**
     * Write a generated file, asking before overwriting an existing one.
     */
    protected function generate(string $path, string $content, string $label): bool
    {
        $relative = $this->relativePath($path);

        if ($this->isDryRun()) {
            $this->line("<comment>[dry-run]</comment> {$label} would be written to {$relative}");
            $this->line($content);

            return true;
        }

        if (is_file($path) && ! $this->isForced()) {
            $replace = $this->input->isInteractive()
                && $this->confirm("{$label} already exists ({$relative}). Replace it?", false);

            if (! $replace) {
                $this->warn("{$label} skipped: {$relative} already exists.");

                return false;
            }
        }

        $this->files()->ensureDirectoryExists(dirname($path));
        $this->files()->put($path, $content);
        $this->info("{$label} created: {$relative}");

        return true;
    }

    protected function isForced(): bool
    {
        return $this->hasOption('force') && (bool) $this->option('force');
    }

    protected function isDryRun(): bool
    {
        return $this->hasOption('dry-run') && (bool) $this->option('dry-run');
    }

    protected function relativePath(string $path): string
    {
        return ltrim(Str::after($path, $this->laravel->basePath()), '/\\');
    }

    /**
     * Split "Admin/UserRepository" or "Admin\UserRepository" into [sub-namespace, class].
     *
     * @return array{0: string, 1: string}
     */
    protected function parseName(string $name): array
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $name)) {
            throw new InvalidArgumentException("Invalid class name \"{$name}\".");
        }

        $parts = array_map(fn (string $part) => Str::studly($part), explode('\\', $name));
        $class = array_pop($parts);

        return [implode('\\', $parts), $class];
    }

    /**
     * Resolve the file path, namespace and class name of a class to generate.
     *
     * @param  string  $folder  Folder relative to app/ (or to the module), e.g. "Http/Resources".
     * @return array{path: string, namespace: string, class: string}
     */
    protected function classTarget(string $name, string $folder, ?string $module = null): array
    {
        [$sub, $class] = $this->parseName($name);
        $subPath = $sub === '' ? '' : str_replace('\\', '/', $sub).'/';

        if ($module) {
            $directory = $this->modulePath($module, $folder);
            $namespace = $this->moduleNamespace($module, $folder);
        } else {
            $directory = $this->laravel->path($folder);
            $namespace = $this->rootNamespace().'\\'.str_replace('/', '\\', $folder);
        }

        return [
            'path' => rtrim($directory, '/').'/'.$subPath.$class.'.php',
            'namespace' => $namespace.($sub === '' ? '' : '\\'.$sub),
            'class' => $class,
        ];
    }

    protected function rootNamespace(): string
    {
        return rtrim($this->laravel->getNamespace(), '\\');
    }

    protected function moduleName(string $module): string
    {
        return Str::studly($module);
    }

    protected function moduleBasePath(string $module): string
    {
        return $this->laravel->basePath('Modules/'.$this->moduleName($module));
    }

    protected function moduleExists(string $module): bool
    {
        return is_dir($this->moduleBasePath($module));
    }

    /**
     * nwidart/laravel-modules < 10 puts classes at the module root (Entities, Http, ...),
     * version 10+ puts them in an app/ folder (Models, Http, ...).
     */
    protected function isLegacyModule(string $module): bool
    {
        return ! is_dir($this->moduleBasePath($module).'/app');
    }

    protected function ensureModuleExists(string $module): void
    {
        if (! $this->moduleExists($module)) {
            throw new InvalidArgumentException("The module \"{$this->moduleName($module)}\" does not exist.");
        }
    }

    /**
     * Absolute path of a class folder inside a module.
     */
    protected function modulePath(string $module, string $folder = ''): string
    {
        $this->ensureModuleExists($module);

        $base = $this->moduleBasePath($module).($this->isLegacyModule($module) ? '' : '/app');

        return rtrim($base.'/'.trim($folder, '/'), '/');
    }

    protected function moduleNamespace(string $module, string $folder = ''): string
    {
        $namespace = 'Modules\\'.$this->moduleName($module);
        $folder = trim(str_replace('/', '\\', $folder), '\\');

        return $folder === '' ? $namespace : $namespace.'\\'.$folder;
    }

    protected function modelsFolder(?string $module = null): string
    {
        return $module && $this->isLegacyModule($module) ? 'Entities' : 'Models';
    }

    /**
     * Resolve a model option (e.g. "User", "Admin/User", "App\Models\User") to a FQCN.
     */
    protected function resolveModel(string $model, ?string $module = null): string
    {
        $model = trim(str_replace('/', '\\', $model), '\\');

        if (Str::startsWith($model, [$this->rootNamespace().'\\', 'Modules\\'])) {
            return $model;
        }

        [$sub, $class] = $this->parseName($model);
        $base = $module
            ? $this->moduleNamespace($module, $this->modelsFolder($module))
            : $this->rootNamespace().'\\Models';

        return $base.($sub === '' ? '' : '\\'.$sub).'\\'.$class;
    }

    /**
     * Check if a model exists, either autoloadable or as a file.
     */
    protected function modelExists(string $fqcn): bool
    {
        if (class_exists($fqcn)) {
            return true;
        }

        $root = $this->rootNamespace().'\\';

        if (Str::startsWith($fqcn, $root)) {
            return is_file($this->laravel->path(str_replace('\\', '/', Str::after($fqcn, $root)).'.php'));
        }

        if (preg_match('/^Modules\\\\([^\\\\]+)\\\\(.+)$/', $fqcn, $matches) && $this->moduleExists($matches[1])) {
            return is_file($this->modulePath($matches[1], str_replace('\\', '/', $matches[2])).'.php');
        }

        return false;
    }

    protected function classBasename(string $fqcn): string
    {
        return class_basename($fqcn);
    }
}
