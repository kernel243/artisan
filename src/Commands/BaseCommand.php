<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BaseCommand extends Command
{

    /**
     * Check if a module folder exists
     *
     * @param $module
     * @return false|mixed
     */
    protected function moduleExists($module)
    {
        return $this->folderExist('Modules/'.$module);
    }

    /**
     * Checks if a folder exist and return canonicalized absolute pathname (sort version)
     * @param string $folder the path being checked.
     * @return string|false returns the canonicalized absolute pathname on success otherwise FALSE is returned
     */
    protected function folderExist(string $folder)
    {
        // Get canonicalized absolute pathname
        $path = realpath($folder);

        // If it exists, check if it's a directory
        return ($path !== false && is_dir($path)) ? $path : false;
    }

    /**
     * Replace every DummyProperty with the right property name.
     *
     * @param $name
     * @param $stub
     * @return mixed
     */
    protected function replacePropertyName($name, $stub)
    {
        $property = lcfirst(Str::camel($name));
        return str_replace('DummyProperty', $property, $stub);
    }

    /**
     * Replace every DummyModel with the right model name.
     *
     * @param $name
     * @param $stub
     * @return mixed
     */
    protected function replaceModelName($name, $stub)
    {
        $model = ucfirst($name);
        return str_replace('DummyModel', $model, $stub);
    }

    /**
     * Replace every DummyResource with the right resource name.
     *
     * @param $name
     * @param $stub
     * @return mixed
     */
    protected function replaceResourceName($name, $stub)
    {
        $model = ucfirst($name);
        return str_replace('DummyResource', $model, $stub);
    }

    /**
     * Replace every DummyClass with the right class name.
     *
     * @param $name
     * @param $stub
     * @return mixed
     */
    protected function replaceClassName($name, $stub)
    {
        $class = ucfirst($name);
        return str_replace('DummyClass', $class, $stub);
    }

    /**
     * Replace the namespace of the namespace of the model.
     *
     * @param $namespace
     * @param $stub
     * @return mixed
     */
    protected function replaceModelNamespace($namespace, $stub)
    {
        return str_replace('DummyModelNamespace', ucfirst($namespace), $stub);
    }

    /**
     * Replace the namespace
     *
     * @param $namespace
     * @param $stub
     * @return mixed
     */
    protected function replaceNamespace($namespace, $stub)
    {
        return str_replace('DummyNamespace', ucfirst($namespace), $stub);
    }

    /**
     * Replace the namespace of the namespace of the repository.
     *
     * @param $namespace
     * @param $stub
     * @return mixed
     */
    protected function replaceRepositoryNamespace($namespace, $stub)
    {
        return str_replace('DummyNamespaceRepository', ucfirst($namespace), $stub);
    }

    /**
     * Replace the namespace of the namespace of the resource.
     *
     * @param $namespace
     * @param $stub
     * @return mixed
     */
    protected function replaceResourceNamespace($namespace, $stub)
    {
        return str_replace('DummyNamespaceResource', ucfirst($namespace), $stub);
    }

    /**
     * Read a stub file by logical name from this package stubs directory.
     */
    protected function getStubContent(string $name): string
    {
        $path = __DIR__ . '/stubs/' . str_replace('.', '/', $name) . '.stub';
        if (!file_exists($path)) {
            throw new \RuntimeException('Stub not found: ' . $path);
        }
        return (string) file_get_contents($path);
    }

    /**
     * Write content to a file, creating parent directories when needed.
     */
    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * Ask whether a file should be replaced unless --force is provided.
     */
    protected function shouldReplaceFile(string $path, string $question = 'Replace file? [y/n]'): bool
    {
        if (!file_exists($path)) {
            return true;
        }
        if ($this->option('force')) {
            return true;
        }
        do {
            $input = strtolower($this->ask($question));
        } while ($input !== 'y' && $input !== 'n');
        return $input === 'y';
    }

    /**
     * Simple success output helper.
     */
    protected function displaySuccess(string $message, string $path): void
    {
        $this->info($message);
        $this->line(' → ' . $path);
    }

    /**
     * Ensure a directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Basic filename validation for ClassMakeCommand.
     */
    protected function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('#^[A-Za-z0-9_\\\\/\.-]+$#', $filename);
    }

    /**
     * Check if a repository file exists.
     *
     * @param $repository
     * @param null $module
     * @return bool
     */
    protected function repositoryFileExists($repository, $module = null): bool
    {
        if (is_null($module)) {
            $repositoryName = ucfirst(basename(str_replace('\\', '/', $repository)));
            return file_exists(app_path('Repositories/'.$repositoryName.'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Repositories/'.ucfirst(basename(str_replace('\\', '/', $repository))).'.php');
        return file_exists($path);
    }

    /**
     * Set the right name and namespace.
     *
     * @param $model
     * @param $namespace
     * @param $module
     * @return void
     */
    protected function setModelAndNamespace(&$model, &$namespace, &$module)
    {
        $exploded = str_contains($model, '/') ? explode('/', $model) : explode('\\', $model);
        $model = Arr::last($exploded);
        $namespace = '';
        $module = '';

        for ($i = 0; $i < count($exploded) - 1; $i++) {
            if (!empty($module)) {
                $namespace .= $module.'\\'.$exploded[$i].'\\'.'Entities\\';
            } else {
                $namespace .= $exploded[$i].'\\';
            }
        }

        $namespace = Str::replaceLast('\\','', $namespace);
    }

    /**
     * Set the right name and namespace.
     *
     * @param $repository
     * @param $namespace
     * @param $module
     * @return void
     */
    protected function setRepositoryAndNamespace(&$repository, &$namespace, &$module)
    {
        $exploded = str_contains($repository, '/') ? explode('/', $repository) : explode('\\', $repository);
        $repository = Arr::last($exploded);
        $namespace = '';
        $module = '';

        for ($i = 0; $i < count($exploded) - 1; $i++) {
            if (!empty($module)) {
                $namespace .= $module.'\\'.$exploded[$i].'\\'.'Repositories\\';
            } else {
                $namespace .= $exploded[$i].'\\';
            }
        }

        $namespace = Str::replaceLast('\\','', $namespace);
    }

    /**
     * Check if a model file exists.
     *
     * @param $model
     * @param null $module
     * @return bool
     */
    protected function modelFileExists($model, $module = null): bool
    {
        if (is_null($module)) {
            $modelName = ucfirst(basename(str_replace('\\', '/', $model)));
            return file_exists(app_path('Models/'.$modelName.'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Entities/'.ucfirst(basename(str_replace('\\', '/', $model))).'.php');
        return file_exists($path);
    }



}
