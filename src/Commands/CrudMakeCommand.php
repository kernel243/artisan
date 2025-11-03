<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;

class CrudMakeCommand extends BaseCommand
{
    protected $signature = 'make:crud {name}
                            {--fields= : Comma-separated fields with their types (e.g., "title:string,name:string")}
                            {--force : Overwrite existing files without confirmation}
                            {--dry-run : Preview the file that would be created without actually creating it}';

    protected $description = 'Create a CRUD resource with Model, Controller, Repository, Service, and Blade views';

    protected function parseFields(?string $fields): array
    {
        if (empty($fields)) {
            return [];
        }
        $parsedFields = [];
        foreach (explode(',', $fields) as $field) {
            $parts = explode(':', trim($field));
            if (count($parts) === 2) {
                $parsedFields[] = ['name' => trim($parts[0]), 'type' => trim($parts[1])];
            }
        }
        return $parsedFields;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $fields = $this->parseFields($this->option('fields'));
        if (empty($name)) {
            $this->error('The name of the CRUD resource is required.');
            return self::FAILURE;
        }
        try {
            $this->generateModel($name, $fields);
            $this->generateRepository($name);
            $this->generateService($name);
            $this->generateController($name);
            $this->generateViews($name, $fields);
            $this->generateRoutes($name);
            $this->generateTailwindConfig();
            $this->info('✓ CRUD generated successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function generateModel(string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.model');
        $stub = str_replace('DummyModel', ucfirst($name), $stub);
        $stub = str_replace('DummyTable', Str::snake(Str::plural($name)), $stub);
        $fillableString = empty($fields) ? "// 'field1', 'field2'" : implode(",\n        ", array_map(fn($f) => "'{$f['name']}'", $fields));
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
        $viewPath = Str::kebab(Str::plural($name));
        $stub = $this->getStubContent('crud.controller');
        $stub = str_replace(['DummyController','DummyService','DummyVariable','DummyPluralVariable','DummyViewPath'],
                            [$modelName.'Controller',$modelName.'Service',$variableName,$pluralVariable,$viewPath], $stub);
        $path = app_path('Http/Controllers/' . $modelName . 'Controller.php');
        if (!$this->shouldReplaceFile($path, "Controller {$name} exists. Replace it? [y/n]")) return;
        $this->ensureDirectoryExists(app_path('Http/Controllers'));
        $this->writeFile($path, $stub);
    }

    protected function generateViews(string $name, array $fields): void
    {
        $viewPath = Str::kebab(Str::plural($name));
        $this->generateIndexView($viewPath, $name, $fields);
        $this->generateCreateView($viewPath, $name, $fields);
        $this->generateEditView($viewPath, $name, $fields);
        $this->generateShowView($viewPath, $name, $fields);
        $this->generateLayout();
    }

    protected function generateIndexView(string $viewPath, string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.views.index');
        $stub = str_replace(['DummyResource','DummyResourceKebab','$items'], [Str::plural(ucfirst($name)), $viewPath, '$'.Str::plural(lcfirst($name))], $stub);
        $path = resource_path('views/' . $viewPath . '/index.blade.php');
        if (!$this->shouldReplaceFile($path, 'Index view exists. Replace it? [y/n]')) return;
        $this->ensureDirectoryExists(resource_path('views/' . $viewPath));
        $this->writeFile($path, $stub);
    }

    protected function generateCreateView(string $viewPath, string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.views.create');
        $stub = str_replace(['DummyResource','DummyResourcePlural','DummyResourceKebab','DummyFormFields'], [ucfirst($name), Str::plural($name), $viewPath, $this->generateFormFields($fields)], $stub);
        $path = resource_path('views/' . $viewPath . '/create.blade.php');
        if (!$this->shouldReplaceFile($path, 'Create view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateEditView(string $viewPath, string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.views.edit');
        $stub = str_replace(['DummyResource','DummyResourcePlural','DummyResourceKebab','DummyVariable','DummyFormFields'], [ucfirst($name), Str::plural($name), $viewPath, lcfirst($name), $this->generateFormFields($fields, true, lcfirst($name))], $stub);
        $path = resource_path('views/' . $viewPath . '/edit.blade.php');
        if (!$this->shouldReplaceFile($path, 'Edit view exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateShowView(string $viewPath, string $name, array $fields): void
    {
        $stub = $this->getStubContent('crud.views.show');
        $stub = str_replace(['DummyResource','DummyResourceKebab','DummyVariable','DummyDetailFields'], [ucfirst($name), $viewPath, lcfirst($name), $this->generateDetailFields($fields, lcfirst($name))], $stub);
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

    protected function generateTailwindConfig(): void
    {
        $path = base_path('tailwind.config.js');
        if (file_exists($path)) return;
        $stub = $this->getStubContent('tailwind.config');
        if (!$this->shouldReplaceFile($path, 'Tailwind config exists. Replace it? [y/n]')) return;
        $this->writeFile($path, $stub);
    }

    protected function generateFormFields(array $fields, bool $forEdit = false, string $variableName = 'item'): string
    {
        if (empty($fields)) { return '            {{-- Add your form fields here --}}'; }
        $lines = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
            $valueBinding = $forEdit ? "{{ old('{$fieldName}', \\$${variableName}->{$fieldName}) }}" : "{{ old('{$fieldName}') }}";
            $lines[] = $this->generateFieldByType($fieldName, $fieldLabel, $valueBinding, $field['type']);
        }
        return implode("\n", $lines);
    }

    protected function generateFieldByType(string $fieldName, string $fieldLabel, string $valueBinding, string $type): string
    {
        $id = "field_{$fieldName}";
        if (in_array($type, ['text','string','varchar'])) {
            return <<<HTML
            <div class="mb-4">
                <label for="$id" class="block text-gray-700 font-bold mb-2">$fieldLabel</label>
                <input type="text" id="$id" name="$fieldName" value="$valueBinding" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       required>
            </div>
HTML;
        } elseif (in_array($type, ['textarea'])) {
            return <<<HTML
            <div class="mb-4">
                <label for="$id" class="block text-gray-700 font-bold mb-2">$fieldLabel</label>
                <textarea id="$id" name="$fieldName" rows="4"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                          required>$valueBinding</textarea>
            </div>
HTML;
        } elseif ($type === 'email') {
            return <<<HTML
            <div class="mb-4">
                <label for="$id" class="block text-gray-700 font-bold mb-2">$fieldLabel</label>
                <input type="email" id="$id" name="$fieldName" value="$valueBinding" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       required>
            </div>
HTML;
        } elseif (in_array($type, ['boolean','tinyint'])) {
            return <<<HTML
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" id="$id" name="$fieldName" value="1" class="mr-2">
                    <span class="text-gray-700">$fieldLabel</span>
                </label>
            </div>
HTML;
        }
        return <<<HTML
            <div class="mb-4">
                <label for="$id" class="block text-gray-700 font-bold mb-2">$fieldLabel</label>
                <input type="text" id="$id" name="$fieldName" value="$valueBinding" 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                       required>
            </div>
HTML;
    }

    protected function generateDetailFields(array $fields, string $variableName = 'item'): string
    {
        if (empty($fields)) { return '            {{-- Add detail fields here --}}'; }
        $detailFields = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));
            $detailFields[] = <<<HTML
            <div class="border-b border-gray-200 py-2">
                <dt class="text-gray-500 font-semibold">$fieldLabel:</dt>
                <dd class="text-gray-900">{{\$$variableName->$fieldName}}</dd>
            </div>
HTML;
        }
        return implode("\n", $detailFields);
    }
}
