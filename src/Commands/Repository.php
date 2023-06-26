<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use function PHPUnit\Framework\isEmpty;

class Repository extends BaseCommand
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
     * Retrieve the stub content from the repository stub file.
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
                            $content = $this->replacePropertyName($model, $content);
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
