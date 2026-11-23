<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithFields;
use Kernel243\Artisan\Commands\Concerns\InteractsWithPermissions;

class RequestMakeCommand extends BaseCommand
{
    use InteractsWithFields;
    use InteractsWithPermissions;

    protected $signature = 'kernel:request {model : The model the requests validate (e.g. Post)}
                            {--fields= : Comma-separated fields, e.g. "title:string,email:email:unique,bio:text?"}
                            {--only= : Generate only the "store" or the "update" request}
                            {--module= : The nwidart module where the requests will be created}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create the Store<Model>Request and Update<Model>Request form requests';

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['store', 'update'], true)) {
            $this->error('The --only option must be "store" or "update".');

            return self::FAILURE;
        }

        try {
            $module = $this->option('module') ?: null;
            [, $model] = $this->parseName($this->argument('model'));
            $modelClass = $this->resolveModel($model, $module);
            $fields = $this->resolveFields($this->option('fields'), $modelClass);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (['store', 'update'] as $kind) {
            if ($only === null || $only === $kind) {
                $target = $this->classTarget(Str::plural($model).'/'.Str::studly($kind).$model.'Request', 'Http/Requests', $module);
                $this->generate($target['path'], $this->buildRequest($target, $model, $fields, $kind), Str::studly($kind).' request');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{path: string, namespace: string, class: string}  $target
     * @param  list<array{name: string, type: string, nullable: bool, unique: bool}>  $fields
     */
    protected function buildRequest(array $target, string $model, array $fields, string $kind): string
    {
        $table = Str::snake(Str::plural($model));
        // Route::resource('blog-posts') names its parameter blog_post.
        $routeParameter = $kind === 'update' ? Str::snake($model) : null;
        $permission = $this->permissionName($model, $kind === 'store' ? 'create' : 'update');
        $inController = (bool) $this->laravel['config']->get('kernel-artisan.requests.authorize_in_controller', true);

        $enums = [];

        $rules = array_map(function (array $field) use ($table, $routeParameter, $model, &$enums) {
            $enum = $this->enumClass($field, $model);

            if ($enum !== null) {
                $enums[] = $enum;
            }

            return "            '{$field['name']}' => ".$this->validationRules($field, $table, $routeParameter, $enum).',';
        }, $fields);

        $attributes = array_map(
            fn (array $field) => "            '{$field['name']}' => '".str_replace("'", "\\'", Str::lower($this->fieldLabel($field['name'])))."',",
            $fields,
        );

        $content = $this->render($this->getStubContent('form-request'), [
            'DummyNamespace' => $target['namespace'],
            'DummyClass' => $target['class'],
            'DummyAuthorizeComment' => $inController
                ? "L'autorisation est vérifiée par la policy dans le contrôleur."
                : "Seuls les utilisateurs ayant la permission {$permission} peuvent soumettre ce formulaire.",
            'DummyAuthorize' => $inController ? 'true' : "\$this->user()?->can('{$permission}') ?? false",
            'DummyRules' => $rules === [] ? "            // 'name' => ['required', 'string', 'max:255']," : implode("\n", $rules),
            'DummyAttributes' => $attributes === [] ? "            // 'name' => 'nom'," : implode("\n", $attributes),
        ]);

        // Drop the Rule import when no rule needs it, import the enums.
        $imports = ['Illuminate\\Foundation\\Http\\FormRequest', ...array_unique($enums)];

        if (str_contains(Str::after($content, 'class '), 'Rule::')) {
            $imports[] = 'Illuminate\\Validation\\Rule';
        }

        sort($imports);

        $content = str_replace(
            "use Illuminate\\Foundation\\Http\\FormRequest;\nuse Illuminate\\Validation\\Rule;\n",
            implode('', array_map(fn (string $import) => "use {$import};\n", $imports)),
            $content,
        );

        return $content;
    }
}
