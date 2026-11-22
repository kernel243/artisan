<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\GeneratesCrud;

class ResourceMakeCommand extends BaseCommand
{
    use GeneratesCrud;

    /**
     * Generic views shared by every resource CRUD.
     */
    protected const VIEW_PATH = 'resources.crud';

    protected $signature = 'kernel:resource-crud {name : The model name (e.g. Post)}
                            {--fields= : Comma-separated fields with their types (e.g. "title:string,body:text")}
                            {--tailwind-config : Also create a tailwind.config.js (Tailwind CSS v3) when missing}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create a CRUD driven by a Resource class (form, table and rules) with generic Blade views';

    protected $aliases = ['make:resource-crud'];

    public function handle(): int
    {
        try {
            $names = $this->crudNames((string) $this->argument('name'));
            $fields = $this->parseFields($this->option('fields'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $model = $names['model'];

        $this->generateBaseClasses();
        $this->generateModel($model, $fields);
        $this->generateRepository($model);
        $this->generateService($model);
        $this->generateController($names);
        $this->generateResource($names, $fields);
        $this->generateDefaultViews();
        $this->generateLayout();
        $this->generateRoutes($model, $names['kebab']);
        $this->generateTailwindConfig();

        $this->info("Resource CRUD for {$model} generated.");

        return self::SUCCESS;
    }

    /**
     * The base Resource class and the Form / Table builders are only created once.
     */
    protected function generateBaseClasses(): void
    {
        $classes = [
            'Resources/Resource.php' => ['resource.base', 'Base resource'],
            'Builders/Form.php' => ['form.builder', 'Form builder'],
            'Builders/Table.php' => ['table.builder', 'Table builder'],
        ];

        foreach ($classes as $file => [$stub, $label]) {
            $path = $this->laravel->path($file);

            if (! is_file($path)) {
                $this->generate($path, $this->getStubContent($stub), $label);
            }
        }
    }

    /**
     * @param  array{model: string, variable: string, plural: string, kebab: string, table: string}  $names
     */
    protected function generateController(array $names): void
    {
        $model = $names['model'];

        $this->generate($this->laravel->path("Http/Controllers/{$model}Controller.php"), $this->render($this->getStubContent('crud.controller.resource'), [
            'DummyController' => $model.'Controller',
            'DummyServiceClass' => $this->rootNamespace().'\\Services\\'.$model.'Service',
            'DummyService' => $model.'Service',
            'DummyResourceClass' => $model.'Resource',
            'DummyPluralVariable' => $names['plural'],
            'DummyVariable' => $names['variable'],
            'DummyViewPath' => self::VIEW_PATH,
        ]), 'Controller');
    }

    /**
     * @param  array{model: string, variable: string, plural: string, kebab: string, table: string}  $names
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function generateResource(array $names, array $fields): void
    {
        $model = $names['model'];

        $this->generate($this->laravel->path("Resources/{$model}Resource.php"), $this->render($this->getStubContent('crud.resource'), [
            'DummyResourceName' => Str::plural($model),
            'DummyResourceClass' => $model.'Resource',
            'DummyResourcePlural' => Str::headline(Str::plural($model)),
            'DummyResourceKebab' => $names['kebab'],
            'DummyModel' => $model,
            'DummyFormFields' => $this->resourceFormFields($fields),
            'DummyTableColumns' => $this->resourceTableColumns($fields),
            'DummyValidationRules' => $this->validationRules($fields),
        ]), 'Resource');
    }

    protected function generateDefaultViews(): void
    {
        $directory = $this->laravel->resourcePath('views/'.str_replace('.', '/', self::VIEW_PATH));

        foreach (['_form', 'index', 'create', 'edit', 'show'] as $view) {
            $path = "{$directory}/{$view}.blade.php";

            if (! is_file($path) || $this->isForced()) {
                $this->generate($path, $this->getStubContent("crud.views.default.{$view}"), "Generic {$view} view");
            }
        }
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function resourceFormFields(array $fields): string
    {
        if ($fields === []) {
            return "            // Form::text('name', 'Name'),";
        }

        return implode("\n", array_map(function (array $field) {
            $type = match ($field['type']) {
                'text', 'textarea', 'longtext' => 'textarea',
                'email' => 'email',
                'boolean', 'bool', 'tinyint' => 'checkbox',
                default => 'text',
            };

            return "            Form::{$type}('{$field['name']}', '{$this->fieldLabel($field['name'])}'),";
        }, $fields));
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function resourceTableColumns(array $fields): string
    {
        if ($fields === []) {
            return "            // Table::text('id', 'ID'),";
        }

        $columns = ["            Table::text('id', 'ID'),"];

        foreach ($fields as $field) {
            $type = match ($field['type']) {
                'decimal', 'integer', 'int', 'bigint', 'float', 'double' => 'number',
                'boolean', 'bool', 'tinyint' => 'boolean',
                'datetime', 'date', 'timestamp' => 'datetime',
                default => 'text',
            };

            $columns[] = "            Table::{$type}('{$field['name']}', '{$this->fieldLabel($field['name'])}'),";
        }

        $columns[] = '            Table::actions(),';

        return implode("\n", $columns);
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function validationRules(array $fields): string
    {
        if ($fields === []) {
            return "            // 'field' => 'required',";
        }

        return implode("\n", array_map(fn (array $field) => "            '{$field['name']}' => 'required',", $fields));
    }
}
