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
     * @return mixed returns the canonicalized absolute pathname on success otherwise FALSE is returned
     */
    protected  function folderExist($folder)
    {
        // Get canonicalized absolute pathname
        $path = realpath($folder);

        // If it exists, check if it's a directory
        return ($path !== false AND is_dir($path)) ? $path : false;
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
     * Check if a repository file exists.
     *
     * @param $repository
     * @param null $module
     * @return bool
     */
    protected function repositoryFileExists($repository, $module = null): bool
    {
        if (is_null($module)) {
            return file_exists( base_path(lcfirst($repository).'.php')) || file_exists( base_path(lcfirst(str_replace('\\', '/', $repository)).'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Repositories/'.lcfirst($repository).'.php');
        return file_exists($path) || file_exists('Modules/'.base_path(ucfirst($module).'/Repositories/'.lcfirst(str_replace('\\', '/', $repository)).'.php'));
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
            if (!isEmpty($module)) {
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
            if (!isEmpty($module)) {
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
            return file_exists( base_path(lcfirst($model).'.php')) || file_exists( base_path(lcfirst(str_replace('\\', '/', $model)).'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Entities/'.lcfirst($model).'.php');
        return file_exists($path) || file_exists('Modules/'.base_path(ucfirst($module).'/Entities/'.lcfirst(str_replace('\\', '/', $model)).'.php'));
    }



}
