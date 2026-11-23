<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithFields;
use Spatie\LaravelData\Data;

class DataMakeCommand extends BaseCommand
{
    use InteractsWithFields;

    protected $signature = 'kernel:data {name : The DTO name (e.g. PostData or Posts/PostData)}
                            {--model= : Read the fields from this model (fillable attributes and casts)}
                            {--fields= : Comma-separated fields, e.g. "title:string,price:decimal,bio:text?"}
                            {--update : Partial update DTO: every property becomes Optional}
                            {--module= : The nwidart module where the DTO will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a spatie/laravel-data DTO';

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $modelClass = $this->option('model') ? $this->resolveModel($this->option('model'), $module) : null;
            $target = $this->classTarget($this->domainName($this->argument('name'), $modelClass), 'Data', $module);
            $fields = $this->resolveFields($this->option('fields'), $modelClass);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! class_exists(Data::class)) {
            $this->warn('spatie/laravel-data is not installed: composer require spatie/laravel-data');
        }

        $this->generate($target['path'], $this->buildData($target, $fields, (bool) $this->option('update'), $modelClass), 'Data');

        return self::SUCCESS;
    }

    /**
     * Without a sub-folder, a DTO based on a model goes to Data/<Models>/: Data/Posts/PostData.
     */
    protected function domainName(string $name, ?string $modelClass): string
    {
        $name = str_replace('\\', '/', $name);

        if ($modelClass === null || str_contains($name, '/')) {
            return $name;
        }

        return Str::plural($this->classBasename($modelClass)).'/'.$name;
    }

    /**
     * @param  array{path: string, namespace: string, class: string}  $target
     * @param  list<array{name: string, type: string, nullable: bool, unique: bool}>  $fields
     */
    protected function buildData(array $target, array $fields, bool $partial, ?string $modelClass): string
    {
        $imports = ['Spatie\\LaravelData\\Data'];
        $properties = [];

        $model = $modelClass ? $this->classBasename($modelClass) : Str::before(Str::before($target['class'], 'UpdateData'), 'Data');

        foreach ($fields as $field) {
            $type = $this->phpType($field);
            $enum = $this->enumClass($field, $model);

            // laravel-data casts the submitted value to the enum, toArray() gives the value back.
            if ($enum !== null) {
                $imports[] = $enum;
                $type = class_basename($enum);
            }

            $types = [$type];

            if ($partial) {
                array_unshift($types, 'Optional');
            }

            if ($field['nullable']) {
                $types[] = 'null';
            }

            // Pint (nullable_type_declaration) prefers ?string to string|null.
            $declaration = match (true) {
                count($types) === 1 => $type,
                count($types) === 2 && $field['nullable'] => '?'.$type,
                default => implode('|', $types),
            };

            $properties[] = "        public {$declaration} \${$field['name']},";
        }

        if ($partial) {
            $imports[] = 'Spatie\\LaravelData\\Optional';
        }

        sort($imports);

        return $this->render($this->getStubContent('data'), [
            'DummyNamespace' => $target['namespace'],
            'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", array_unique($imports))),
            'DummyClass' => $target['class'],
            'DummyModel' => $modelClass ? $this->classBasename($modelClass) : Str::before($target['class'], 'Data'),
            'DummyProperties' => $properties === [] ? '        // public string $name,' : implode("\n", $properties),
        ]);
    }
}
