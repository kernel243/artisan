<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\GeneratesCrud;

class CrudMakeCommand extends BaseCommand
{
    use GeneratesCrud;

    protected $signature = 'kernel:crud {name : The model name (e.g. Post)}
                            {--fields= : Comma-separated fields with their types (e.g. "title:string,body:text,published:boolean")}
                            {--tailwind-config : Also create a tailwind.config.js (Tailwind CSS v3) when missing}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create a Blade CRUD: model, repository, service, controller, views and routes';

    protected $aliases = ['make:crud'];

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

        $this->generateModel($model, $fields);
        $this->generateRepository($model);
        $this->generateService($model);
        $this->generateController($names);
        $this->generateViews($names, $fields);
        $this->generateLayout();
        $this->generateRoutes($model, $names['kebab']);
        $this->generateTailwindConfig();

        $this->info("CRUD for {$model} generated.");

        return self::SUCCESS;
    }

    /**
     * @param  array{model: string, variable: string, plural: string, kebab: string, table: string}  $names
     */
    protected function generateController(array $names): void
    {
        $model = $names['model'];

        $this->generate($this->laravel->path("Http/Controllers/{$model}Controller.php"), $this->render($this->getStubContent('crud.controller'), [
            'DummyController' => $model.'Controller',
            'DummyServiceClass' => $this->rootNamespace().'\\Services\\'.$model.'Service',
            'DummyService' => $model.'Service',
            'DummyPluralVariable' => $names['plural'],
            'DummyVariable' => $names['variable'],
            'DummyViewPath' => $names['kebab'],
        ]), 'Controller');
    }

    /**
     * @param  array{model: string, variable: string, plural: string, kebab: string, table: string}  $names
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function generateViews(array $names, array $fields): void
    {
        $directory = $this->laravel->resourcePath('views/'.$names['kebab']);
        $title = Str::headline($names['model']);

        $common = [
            'DummyResourceKebab' => $names['kebab'],
            'DummyResourcePlural' => Str::headline($names['plural']),
            'DummyVariable' => $names['variable'],
        ];

        $this->generate($directory.'/index.blade.php', $this->render($this->getStubContent('crud.views.index'), $common + [
            'DummyResource' => Str::headline($names['plural']),
            '$items' => '$'.$names['plural'],
        ]), 'Index view');

        $this->generate($directory.'/create.blade.php', $this->render($this->getStubContent('crud.views.create'), $common + [
            'DummyResource' => $title,
            'DummyFormFields' => $this->formFields($fields),
        ]), 'Create view');

        $this->generate($directory.'/edit.blade.php', $this->render($this->getStubContent('crud.views.edit'), $common + [
            'DummyResource' => $title,
            'DummyFormFields' => $this->formFields($fields, $names['variable']),
        ]), 'Edit view');

        $this->generate($directory.'/show.blade.php', $this->render($this->getStubContent('crud.views.show'), $common + [
            'DummyResource' => $title,
            'DummyDetailFields' => $this->detailFields($fields, $names['variable']),
        ]), 'Show view');
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function formFields(array $fields, ?string $variable = null): string
    {
        if ($fields === []) {
            return '{{-- Add your form fields here --}}';
        }

        $input = 'shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline';
        $html = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $this->fieldLabel($name);
            $id = "field_{$name}";
            $value = $variable === null ? "old('{$name}')" : "old('{$name}', \${$variable}->{$name})";

            $html[] = match (true) {
                in_array($field['type'], ['text', 'textarea', 'longtext'], true) => <<<HTML
            <div class="mb-4">
                <label for="{$id}" class="block text-gray-700 font-bold mb-2">{$label}</label>
                <textarea id="{$id}" name="{$name}" rows="4" class="{$input}" required>{{ {$value} }}</textarea>
            </div>
HTML,
                in_array($field['type'], ['boolean', 'bool', 'tinyint'], true) => <<<HTML
            <div class="mb-4">
                <input type="hidden" name="{$name}" value="0">
                <label class="flex items-center">
                    <input type="checkbox" id="{$id}" name="{$name}" value="1" class="mr-2" @checked({$value})>
                    <span class="text-gray-700">{$label}</span>
                </label>
            </div>
HTML,
                default => <<<HTML
            <div class="mb-4">
                <label for="{$id}" class="block text-gray-700 font-bold mb-2">{$label}</label>
                <input type="{$this->inputType($field['type'])}" id="{$id}" name="{$name}" value="{{ {$value} }}" class="{$input}" required>
            </div>
HTML,
            };
        }

        return ltrim(implode("\n", $html));
    }

    protected function inputType(string $type): string
    {
        return match ($type) {
            'email' => 'email',
            'integer', 'int', 'bigint', 'decimal', 'float', 'double' => 'number',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'password' => 'password',
            default => 'text',
        };
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     */
    protected function detailFields(array $fields, string $variable): string
    {
        if ($fields === []) {
            return '{{-- Add detail fields here --}}';
        }

        $html = [];

        foreach ($fields as $field) {
            $html[] = <<<HTML
                <div class="border-b border-gray-200 py-2">
                    <dt class="text-gray-500 font-semibold">{$this->fieldLabel($field['name'])}</dt>
                    <dd class="text-gray-900">{{ \${$variable}->{$field['name']} }}</dd>
                </div>
HTML;
        }

        return ltrim(implode("\n", $html));
    }
}
