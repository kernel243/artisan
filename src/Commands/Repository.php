<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use function PHPUnit\Framework\isEmpty;

class Repository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name} {--model= : The model on which the repository class will be based on} {--module= : The module on which the repository class will be based on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieve the stub content from the repository's stub file.
     *
     * @return mixed
     */
    protected function getStub()
    {
        return file_get_contents(__DIR__.'/stubs/repository.stub');
    }

    /**
     * Retrieve the stub content from the repository's empty stub file.
     *
     * @return bool|string
     */
    protected function getEmptyStub()
    {
        return file_get_contents(__DIR__.'/stubs/empty.repository.stub');
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
     * Rewrite actually the content in the file.
     * @param null $module
     * @param $filename
     * @param $content
     */
    protected function putInFile($filename, $content, $module = null)
    {
       if (!is_null($module)) {
           $modulePath = base_path('Modules/'.$module.'/Repositories');
           if (!is_dir($modulePath)) mkdir($modulePath);
       } else {
           if (!is_dir(app_path('/Repositories')))
               mkdir(app_path('/Repositories'));
       }

        file_put_contents($filename, $content);
    }

    /**
     * Set the right name and namespace.
     *
     * @param $model
     * @param $namespace
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
     * Check if a model file exists.
     *
     * @param $model
     * @return bool
     */
    protected function modelFileExists($model, $module = null)
    {
        if (is_null($module)) {
            return file_exists( base_path(lcfirst($model).'.php')) || file_exists( base_path(lcfirst(str_replace('\\', '/', $model)).'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Entities/'.lcfirst($model).'.php');
        return file_exists($path) || file_exists('Modules/'.base_path(ucfirst($module).'/Entities/'.lcfirst(str_replace('\\', '/', $model)).'.php'));
    }

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
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $name = $this->argument('name');
        $model = $this->option('model');
        $module = $this->option('module');
        $modelNameSpace = 'App\\Models';
        $namespace = 'App\\Repositories';
        if (empty($name)) {
            $this->error('Please the name of the repository is expected.');
        } else {
            $content = null;

            if (is_null($model)) {
                $content = $this->replaceClassName($name, $this->getEmptyStub());
            } else {
                if (Str::contains($model, ['\\', '/'])) {
                    $this->setModelAndNamespace($model, $modelNameSpace, $module);
                }

                if (is_null($module)) {
                    if ($this->modelFileExists($modelNameSpace.'\\'.$model)) {
                        $content = $this->replaceNamespace($namespace, $this->getStub());
                        $content = $this->replaceModelNamespace($modelNameSpace, $content);
                        $content = $this->replaceModelName($model, $content);
                        $content = $this->replacePropertyName($model, $content);
                        $content = $this->replaceClassName($name, $content);
                    } else {
                        $this->output->error('The specified model "'.$this->option('model').'" does not exist.');
                    }
                } else {
                    if ($this->moduleExists(ucfirst($module))) {
                        if ($this->modelFileExists($model, $module)) {
                            $modelNameSpace = 'Modules\\'.$module.'\\Entities';
                            $namespace = 'Modules\\'.ucfirst($module).'\\Repositories';
                            $content = $this->replaceNamespace($namespace, $this->getStub());
                            $content = $this->replaceModelNamespace($modelNameSpace, $content);
                            $content = $this->replaceModelName($model, $content);
                            $content = $this->replacePropertyName($name, $content);
                            $content = $this->replaceClassName($name, $content);
                        } else {
                            $this->output->error('The specified model "'.$this->option('model').'" does not exist.');
                        }
                    } else {
                        $this->output->error('The specified module "'.$this->option('module').'" does not exist.');
                    }
                }
            }

            if (!is_null($content)) {
                $filename = !is_null($module) ? base_path('Modules/'.$module.'/Repositories/'.ucfirst($name).'.php') : app_path('Repositories/'.ucfirst($name).'.php');

                if (file_exists($filename)) {
                    do {
                        $input = $this->ask("There is a repository with this name ($name) do you want to replace it ? [o/n] ");
                    } while (strtolower($input) != 'o' && strtolower($input) != 'n');

                    if('o' == strtolower($input)) {
                        $this->putInFile($filename, $content, $module);
                        $this->info('Reporitory created successfully.');
                    }
                } else {
                    $this->putInFile($filename, $content, $module);
                    $this->info('Reporitory created successfully.');
                }
            }
        }
    }
}
