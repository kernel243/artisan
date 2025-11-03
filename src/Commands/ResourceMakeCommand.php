<?php

declare(strict_types=1);

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;

class ResourceMakeCommand extends BaseCommand
{
    protected $signature = 'make:resource-crud {name}
                            {--fields= : Comma-separated fields with their types (e.g., "title:string,name:string")}
                            {--force : Overwrite existing files without confirmation}
                            {--dry-run : Preview the file that would be created without actually creating it}';

    protected $description = 'Create a CRUD resource with Resource classes (Filament-inspired pattern)';

    protected function parseFields(?string $fields): array
    {
        if (empty($fields)) { return []; }
        $parsed = [];
        foreach (explode(',', $fields) as $field) {
            $parts = explode(':', trim($field));
            if (count($parts) === 2) { $parsed[] = ['name'=>trim($parts[0]), 'type'=>trim($parts[1])]; }
        }
        return $parsed;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $fields = $this->parseFields($this->option('fields'));
        if (empty($name)) { $this->error('The name of the CRUD resource is required.'); return self::FAILURE; }
        try {
            $this->generateBaseClasses();
            $this->generateModel($name, $fields);
            $this->generateRepository($name);
            $this->generateService($name);
            $this->generateController($name);
            $this->generateResource($name, $fields);
            $this->generateDefaultViews($name, $fields);
            $this->generateRoutes($name);
            $this->generateTailwindConfig();
            $this->info('✓ Resource CRUD generated successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function generateBaseClasses(): void
    {
        $this->generateBaseResource();
        $this->generateFormBuilder();
        $this->generateTableBuilder();
    }

    protected function generateBaseResource(): void
    {
        $path = app_path('Resources/Resource.php');
        if (file_exists($path)) return;
        $stub = $this->getStubContent('resource.base');
        if (!$this->shouldReplaceFile($path, 'Base Resource exists. Replace it? [y/n]')) return;
        $this->ensureDirectoryExists(app_path('Resources'));
        $this->writeFile($path, $stub);
    }

    protected function generateFormBuilder(): void
    {
        $path = app_path('Builders/Form.php');
        if (file_exists($path)) return;
        $stub = $this->getStubContent('form.builder');
        if (!$this->shouldReplaceFile($path, 'Form builder exists. Replace it? [y/n]')) return;
        $this->ensureDirectoryExists(app_path('Builders'));
        $this->writeFile($path, $stub);
    }

    protected function generateTableBuilder(): void
    {
        $path = app_path('Builders/Table.php');
        if (file_exists($path)) return;
        $stub = $this->getStubContent('table.builder');
        if (!$this->shouldReplaceFile($path, 'Table builder exists. Replace it? [y/n]')) return;
        $this->ensureDirectoryExists(app_path('Builders'));
        $this->writeFile($path, $stub);
    }

    protected function generateModel(string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.model');
        $stub = str_replace('DummyModel', ucfirst($name), $stub);
        $stub = str_replace('DummyTable', Str::snake(Str::plural($name)), $stub);
        $fillableString = empty($fields) ? "// 'field1', 'field2'" : implode(",\n        ", array_map(fn($f)=>"'{$f['name']}'", $fields));
        $stub = str_replace('DummyFillable', $fillableString, $stub);
        $path = app_path('Models/' . ucfirst($name) . '.php');
        if (!$this->shouldReplaceFile($path, "Model {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Models'));
        $this->writeFile($path, $stub);
    }

    protected function generateRepository(string $name): void
    {
        $modelName = ucfirst($name);
        $stub = $this->getStubContent('repository');
        $stub = str_replace('DummyModelNamespace', 'App', $stub);
        $stub = str_replace('DummyModel', $modelName, $stub);
        $stub = str_replace('DummyProperty', lcfirst(Str::camel($modelName)), $stub);
        $stub = str_replace('class DummyClass', 'class ' . $modelName . 'Repository', $stub);
        $path = app_path('Repositories/' . $modelName . 'Repository.php');
        if (!$this->shouldReplaceFile($path, "Repository {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Repositories'));
        $this->writeFile($path, $stub);
    }

    protected function generateService(string $name): void
    {
        $modelName = ucfirst($name);
        $stub = $this->getStubContent('crud.service');
        $stub = str_replace('DummyClass', $modelName . 'Service', $stub);
        $stub = str_replace('DummyModel', $modelName, $stub);
        $stub = str_replace('DummyRepository', $modelName . 'Repository', $stub);
        $path = app_path('Services/' . $modelName . 'Service.php');
        if (!$this->shouldReplaceFile($path, "Service {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Services'));
        $this->writeFile($path, $stub);
    }

    protected function generateController(string $name): void
    {
        $modelName = ucfirst($name);
        $variableName = lcfirst($name);
        $pluralVariable = Str::plural($variableName);
        $viewPath = 'resources.crud';
        $stub = $this->getStubContent('crud.controller.resource');
        $stub = str_replace(['DummyController','DummyService','DummyVariable','DummyPluralVariable','DummyViewPath','DummyResourceClass'],
                            [$modelName.'Controller',$modelName.'Service',$variableName,$pluralVariable,$viewPath,$modelName.'Resource'], $stub);
        $path = app_path('Http/Controllers/' . $modelName . 'Controller.php');
        if (!$this->shouldReplaceFile($path, "Controller {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Http/Controllers'));
        $this->writeFile($path, $stub);
    }

    protected function generateResource(string $name, array $fields): void
    {
        $modelName = ucfirst($name);
        $resourceName = Str::plural($modelName);
        $routePrefix = Str::kebab(Str::plural($name));
        $stub = $this->getStubContent('crud.resource');
        $stub = str_replace(['DummyResourceName','DummyResourceClass','DummyModel','DummyResourcePlural','DummyResourceKebab'],
                            [$resourceName,$modelName.'Resource',$modelName,Str::plural($modelName),$routePrefix], $stub);
        $formFields = $this->generateResourceFormFields($fields);
        $stub = str_replace('DummyFormFields', $formFields, $stub);
        $tableColumns = $this->generateResourceTableColumns($fields);
        $stub = str_replace('DummyTableColumns', $tableColumns, $stub);
        $validationRules = $this->generateValidationRules($fields);
        $stub = str_replace('DummyValidationRules', $validationRules, $stub);
        $path = app_path('Resources/' . $modelName . 'Resource.php');
        if (!$this->shouldReplaceFile($path, "Resource {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Resources'));
        $this->writeFile($path, $stub);
    }

    protected function generateDefaultViews(string $name, array $fields): void
    {
        $viewPath = 'resources.crud';
        $resourceName = Str::plural(ucfirst($name));
        $routePrefix = Str::kebab(Str::plural($name));
        $this->ensureDirectoryExists(resource_path('views/resources/crud'));
        $this->generateDefaultFormView($name, $fields, $viewPath);
        $this->generateDefaultIndexView($name, $fields, $viewPath, $resourceName, $routePrefix);
        $this->generateDefaultCreateView($name, $viewPath, $resourceName, $routePrefix);
        $this->generateDefaultEditView($name, $viewPath, $resourceName, $routePrefix);
        $this->generateDefaultShowView($name, $fields, $viewPath, $resourceName, $routePrefix);
        $this->generateLayout();
    }

    protected function generateDefaultFormView(string $name, array $fields, string $viewPath): void
    {
        $path = resource_path('views/' . $viewPath . '/_form.blade.php');
        if (!$this->shouldReplaceFile($path, '_form view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $this->getStubContent('crud.views.default._form'));
    }

    protected function generateDefaultIndexView(string $name, array $fields, string $viewPath, string $resourceName, string $routePrefix): void
    {
        $stub = $this->getStubContent('crud.views.default.index');
        $stub = str_replace(['{{ $resourceName }}','{{ $routePrefix }}'], [$resourceName,$routePrefix], $stub);
        $path = resource_path('views/' . $viewPath . '/index.blade.php');
        if (!$this->shouldReplaceFile($path, 'Index view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateDefaultCreateView(string $name, string $viewPath, string $resourceName, string $routePrefix): void
    {
        $stub = $this->getStubContent('crud.views.default.create');
        $stub = str_replace(['{{ $resourceName }}','{{ $routePrefix }}'], ['New ' . ucfirst($name), $routePrefix], $stub);
        $path = resource_path('views/' . $viewPath . '/create.blade.php');
        if (!$this->shouldReplaceFile($path, 'Create view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateDefaultEditView(string $name, string $viewPath, string $resourceName, string $routePrefix): void
    {
        $stub = $this->getStubContent('crud.views.default.edit');
        $stub = str_replace(['{{ $resourceName }}','{{ $routePrefix }}'], [ucfirst($name), $routePrefix], $stub);
        $path = resource_path('views/' . $viewPath . '/edit.blade.php');
        if (!$this->shouldReplaceFile($path, 'Edit view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateDefaultShowView(string $name, array $fields, string $viewPath, string $resourceName, string $routePrefix): void
    {
        $stub = $this->getStubContent('crud.views.default.show');
        $stub = str_replace(['{{ $resourceName }}','{{ $routePrefix }}'], [ucfirst($name), $routePrefix], $stub);
        $path = resource_path('views/' . $viewPath . '/show.blade.php');
        if (!$this->shouldReplaceFile($path, 'Show view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateRoutes(string $name): void
    {
        $routesPath = base_path('routes/web.php');
        if (!file_exists($routesPath)) { $this->warn('⚠ routes/web.php not found. Routes not added.'); return; }
        $resourceName = Str::plural(ucfirst($name));
        $controllerName = ucfirst($name) . 'Controller';
        $routePrefix = Str::kebab(Str::plural($name));
        $stub = $this->getStubContent('crud.routes');
        $stub = str_replace(['{{ $resourceName }}','{{ $controllerName }}','{{ $routePrefix }}'], [$resourceName,$controllerName,$routePrefix], $stub);
        $routesContent = file_get_contents($routesPath);
        if (str_contains($routesContent, $routePrefix)) { $this->line('⚠ Routes already exist in web.php. Skipping.'); return; }
        $routesContent .= "\n" . $stub;
        if (!$this->option('dry-run')) {
            file_put_contents($routesPath, $routesContent);
            $this->line('✓ Routes added to web.php');
        } else {
            $this->line('[DRY RUN] Would add routes to web.php');
        }
    }

    protected function generateLayout(): void
    {
        $path = resource_path('views/layouts/app.blade.php');
        if (file_exists($path)) return;
        $stub = $this->getStubContent('crud.layout');
        if (!$this->shouldReplaceFile($path, 'Layout exists. Replace it? [y/n]')) return;
        $this->ensureDirectoryExists(resource_path('views/layouts'));
        $this->writeFile($path, $stub);
    }

    protected function generateResourceFormFields(array $fields): string
    {
        if (empty($fields)) { return "            // Form::text('name', 'Name'),"; }
        $lines = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
            $type = $this->getFormFieldType($field['type']);
            $lines[] = "            Form::{$type}('{$fieldName}', '{$fieldLabel}'),";
        }
        return implode("\n", $lines);
    }

    protected function generateResourceTableColumns(array $fields): string
    {
        if (empty($fields)) { return "            // Table::text('id', 'ID'),"; }
        $columns = ["            Table::text('id', 'ID'),"];        
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
            $type = $this->getTableColumnType($field['type']);
            $columns[] = "            Table::{$type}('{$fieldName}', '{$fieldLabel}'),";
        }
        $columns[] = "            Table::actions(),";
        return implode("\n", $columns);
    }

    protected function generateValidationRules(array $fields): string
    {
        if (empty($fields)) { return "            // 'field' => 'required',"; }
        return implode("\n", array_map(fn($f)=>"            '{$f['name']}' => 'required',", $fields));
    }

    protected function getFormFieldType(string $type): string
    {
        $map = ['string'=>'text','text'=>'textarea','textarea'=>'textarea','email'=>'email','boolean'=>'checkbox','tinyint'=>'checkbox'];
        return $map[strtolower($type)] ?? 'text';
    }

    protected function getTableColumnType(string $type): string
    {
        $map = ['decimal'=>'number','integer'=>'number','bigint'=>'number','boolean'=>'boolean','tinyint'=>'boolean','datetime'=>'datetime','date'=>'datetime','timestamp'=>'datetime'];
        return $map[strtolower($type)] ?? 'text';
    }
}
