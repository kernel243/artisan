<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

class ActionMakeCommand extends BaseCommand
{
    protected const TYPES = ['create', 'update', 'delete', 'plain'];

    protected const METHODS = ['handle', '__invoke', 'execute'];

    protected $signature = 'kernel:action {name : The action name (e.g. CreatePost or Posts/PublishPost)}
                            {--model= : The model the action works on}
                            {--type= : create, update, delete or plain (guessed from the verb of the name)}
                            {--method= : handle, __invoke or execute (default: config kernel-artisan.actions.method)}
                            {--no-data : Take an array instead of a spatie/laravel-data DTO}
                            {--module= : The nwidart module where the action will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create an action class (one business operation per class)';

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $modelClass = $this->option('model') ? $this->resolveModel($this->option('model'), $module) : null;
            $name = $this->actionName((string) $this->argument('name'), $modelClass);
            $target = $this->classTarget($name, 'Actions', $module);
            $type = $this->actionType($target['class']);
            $method = $this->actionMethod();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($type !== 'plain' && $modelClass === null) {
            $this->error("A {$type} action needs a model: use --model=<Model> or --type=plain.");

            return self::FAILURE;
        }

        if ($modelClass !== null && ! $this->modelExists($modelClass)) {
            $this->warn("The model \"{$modelClass}\" does not exist yet.");
        }

        $this->generate($target['path'], $this->buildAction($target, $type, $method, $modelClass, $module), 'Action');

        return self::SUCCESS;
    }

    /**
     * Apply the configured suffix and, without sub-folder, put model actions in Actions/<Models>/.
     */
    protected function actionName(string $name, ?string $modelClass): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $suffix = (string) $this->laravel['config']->get('kernel-artisan.actions.suffix', 'Action');

        if ($suffix !== '' && ! Str::endsWith($name, $suffix)) {
            $name .= $suffix;
        }

        if ($modelClass !== null && ! str_contains($name, '/')) {
            $name = Str::plural($this->classBasename($modelClass)).'/'.$name;
        }

        return $name;
    }

    protected function actionType(string $class): string
    {
        $type = $this->option('type');

        if ($type !== null) {
            if (! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('The --type option must be one of: '.implode(', ', self::TYPES).'.');
            }

            return $type;
        }

        return match (true) {
            Str::startsWith($class, ['Create', 'Store', 'Add', 'Register']) => 'create',
            Str::startsWith($class, ['Update', 'Edit']) => 'update',
            Str::startsWith($class, ['Delete', 'Destroy', 'Remove']) => 'delete',
            default => 'plain',
        };
    }

    protected function actionMethod(): string
    {
        $method = (string) ($this->option('method') ?: $this->laravel['config']->get('kernel-artisan.actions.method', 'handle'));

        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('The method must be one of: '.implode(', ', self::METHODS).'.');
        }

        return $method;
    }

    /**
     * @param  array{path: string, namespace: string, class: string}  $target
     */
    protected function buildAction(array $target, string $type, string $method, ?string $modelClass, ?string $module): string
    {
        $imports = [];
        $transaction = (bool) $this->laravel['config']->get('kernel-artisan.actions.transaction', true);

        if ($modelClass === null || $type === 'plain') {
            if ($modelClass !== null) {
                $imports[] = $modelClass;
            }

            $model = $modelClass ? $this->classBasename($modelClass) : null;
            $variable = $model ? Str::camel($model) : null;

            return $this->renderAction($target, $method, $imports, [
                'description' => 'TODO: expliquer pourquoi cette action existe et quelles règles métier elle garantit.',
                'signature' => $model ? "{$model} \${$variable}" : '',
                'return' => 'void',
                'body' => '        //',
            ]);
        }

        $imports[] = $modelClass;
        $model = $this->classBasename($modelClass);
        $variable = Str::camel($model);
        $label = Str::lower(Str::headline($model));
        $useData = $this->usesData();

        if ($transaction && $type !== 'delete') {
            $imports[] = 'Illuminate\\Support\\Facades\\DB';
        }

        if ($useData && $type !== 'delete') {
            $dataTarget = $this->classTarget(Str::plural($model).'/'.$model.'Data', 'Data', $module);

            // A partial update DTO (<Model>UpdateData, see kernel:data --update) wins when it exists.
            if ($type === 'update') {
                $updateTarget = $this->classTarget(Str::plural($model).'/'.$model.'UpdateData', 'Data', $module);
                $dataTarget = is_file($updateTarget['path']) ? $updateTarget : $dataTarget;
            }

            $imports[] = $dataTarget['namespace'].'\\'.$dataTarget['class'];
            $input = "{$dataTarget['class']} \$data";
            $inputVariable = '$data';
            $payload = '$data->toArray()';
        } else {
            $input = 'array $attributes';
            $inputVariable = '$attributes';
            $payload = '$attributes';
        }

        return match ($type) {
            'create' => $this->renderAction($target, $method, $imports, [
                'description' => "Crée l'enregistrement {$label} à partir des données validées.",
                'signature' => $input,
                'return' => $model,
                'body' => $transaction
                    ? "        return DB::transaction(fn (): {$model} => {$model}::query()->create({$payload}));"
                    : "        return {$model}::query()->create({$payload});",
            ]),
            'update' => $this->renderAction($target, $method, $imports, [
                'description' => "Met à jour l'enregistrement {$label} avec les données validées.",
                'signature' => "{$model} \${$variable}, {$input}",
                'return' => $model,
                'body' => $transaction
                    ? "        return DB::transaction(function () use (\${$variable}, {$inputVariable}): {$model} {\n            \${$variable}->update({$payload});\n\n            return \${$variable}->refresh();\n        });"
                    : "        \${$variable}->update({$payload});\n\n        return \${$variable}->refresh();",
            ]),
            'delete' => $this->renderAction($target, $method, $imports, [
                'description' => "Supprime l'enregistrement {$label}.",
                'signature' => "{$model} \${$variable}",
                'return' => 'void',
                'body' => "        \${$variable}->delete();",
            ]),
        };
    }

    /**
     * @param  list<string>  $imports
     * @param  array{description: string, signature: string, return: string, body: string}  $parts
     */
    protected function renderAction(array $target, string $method, array $imports, array $parts): string
    {
        $imports = array_values(array_unique($imports));
        sort($imports);

        $content = $this->render($this->getStubContent('action'), [
            'DummyNamespace' => $target['namespace'],
            'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", $imports)),
            'DummyDescription' => $parts['description'],
            'DummyClass' => $target['class'],
            'DummyMethod' => $method,
            'DummySignature' => $parts['signature'],
            'DummyReturn' => $parts['return'],
            'DummyBody' => $parts['body'],
        ]);

        // No import: avoid two blank lines after the namespace.
        return str_replace("\n\n\n", "\n\n", $content);
    }

    protected function usesData(): bool
    {
        return ! $this->option('no-data')
            && (bool) $this->laravel['config']->get('kernel-artisan.data.enabled', true)
            && class_exists(Data::class);
    }
}
