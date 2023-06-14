<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;

class Resource extends Command
{
    /**
     * The name and signature of the console command
     * @var string
     */
    protected $signature = 'kernel:resource {name} {--module= : The module on which the repository class will be based on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource class';

    /**
     * Create a new command instance
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
    protected function getEmptyStub()
    {
        return file_get_contents(__DIR__.'/stubs/empty.resource.stub');
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
     * Replace the namespace
     *
     * @param $namespace
     * @param $stub
     * @return array|string|string[]
     */
    protected function replaceNamespace($namespace, $stub)
    {
        return str_replace('DummyNamespace', ucfirst($namespace), $stub);
    }

    /**
     * Rewrite actually the content in the file.
     *
     * @param $filename
     * @param null $content
     * @param $module
     */
    protected function putInFile($filename, $content, $module = null)
    {
        if (!is_null($module)) {
            $modulePath = base_path('Modules/'.ucfirst($module).'/Http/Resources');
            if (!is_dir($modulePath)) mkdir($modulePath);
        } else {
            if (!is_dir(app_path('/Http/Resources')))
                mkdir(app_path('/Http/Resources'));
        }

        file_put_contents($filename, $content);
    }

    /**
     * Check if a resource file exists.
     *
     * @param $resource
     * @param $module
     * @return bool
     */
    protected function resourceFileExists($resource, $module = null)
    {
        if (is_null($module)) {
            return file_exists(base_path(lcfirst($resource).'php')) || file_exists( base_path(lcfirst(str_replace('\\', '/', $resource)).'.php'));
        }

        $path = base_path('Modules/'.ucfirst($module).'/Http/Resources/'.lcfirst($resource).'.php');
        return file_exists($path) || file_exists('Modules/'.base_path(ucfirst($module).'/Entities/'.lcfirst(str_replace('\\', '/', $resource)).'.php'));
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
        $module = $this->option('module');
        $namespace = 'App\\Http\\Resources';

        if (empty($name)) {
            $this->error('Please the name of the service is expected.');
        } else {

            if (!is_null($module)) $namespace = 'Modules\\'.ucfirst($module).'\\Http\\Resources';
            $content = $this->replaceClassName($name, $this->getEmptyStub());
            $content = $this->replaceNamespace($namespace, $content);

            if (!is_null($content)) {

                $filename = app_path('Http/Resources/'.ucfirst($name).'.php');

                if (!is_null($module)) {
                    $filename = base_path('Modules/'.$module.'/Http/Resources/'.ucfirst($name).'.php');
                }

                if (file_exists($filename)) {
                    do {
                        $input = $this->ask("There is a service with this name ($name) do you want to replace it ? [y/n] ");
                    } while (strtolower($input) != 'y' && strtolower($input) != 'n');

                    if ('y' == strtolower($input)) {
                        $this->putInFile($filename, $content, $module);
                        $this->info('Service created successfully.');
                    }
                } else {
                    $this->putInFile($filename, $content, $module);
                    $this->info('Service created successfully.');
                }
            }
        }
    }

}
