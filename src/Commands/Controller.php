<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Controller extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kernel:controller {name}
    {-r : The repository on which the controller will be based on}
    {-R : The resource on which the controller will be based on}
    {--model= : The model on which the controller class will be based on}
    {--module= : The module on which the controller class will be based on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller class';

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
     * Retrieve the stub content from the controller stub file.
     *
     * @return mixed
     */
    protected function getStub()
    {
        return file_get_contents(__DIR__.'/stubs/controller.stub');
    }

    protected function getEmptyStub()
    {
        return file_get_contents(__DIR__.'/stubs/empty.controller.stub');
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
            $modulePath = base_path('Modules/'.$module.'/Http/Controllers');
            if (!is_dir($modulePath)) {
                mkdir($modulePath, 0755, true);
            }
        } else {
            if (!is_dir(app_path('/Http/Controllers'))) {
                mkdir(app_path('/Http/Controllers'), 0755, true);
            }
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
        $repository = $this->argument('-r');
        $resource = $this->argument('-R');
        $module = $this->option('module');
        $modelNamespace = 'App\\Models';
        $repositoryNamespace = 'App\\Repositories';
        $resourceNamespace = 'App\\Http\\Resources';
        $namespace = 'App\\Http\\Controllers';

        if (empty($name)) {
            $this->error('Please the name of controller is expected.');
        } else {
            $content = null;

            if (is_null($model)) {
                $content = $this->replaceClassName($name, $this->getEmptyStub());
            } else {
                if (Str::contains($model, ['\\', '/'])) {
                    $this->setModelAndNamespace($model, $modelNamespace, $module);
                }

                if (is_null($module)) {
                    if ($this->modelFileExists($modelNamespace.'\\'.$model)) {
                        if ($repository) {
                            if (!$this->repositoryFileExists($repositoryNamespace . '\\' . $model . 'Repository')) {
                                $this->call('make:repository', [
                                    'name' => ucfirst($model) . 'Repository',
                                    '--model' => $model
                                ]);
                                $this->output->success('Repository  created .');

                            }

                            $repositoryName = $model . 'Repository';
                            $content = $this->replaceRepositoryNamespace($repositoryNamespace, $this->getStub());
                            $content = $this->replaceNamespace($namespace, $content);
                            $content = $this->replaceModelNamespace($modelNamespace, $content);
                            $content = $this->replaceResourceNamespace($resourceNamespace, $content);
                            $content = $this->replaceModelName($model, $content);
                            $content = $this->replacePropertyName($repositoryName, $content);
                            $content = $this->replaceClassName(ucfirst(Str::camel($name)), $content);
                        }
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
        }
    }

}



