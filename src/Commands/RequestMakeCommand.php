<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithFields;
use Kernel243\Artisan\Commands\Concerns\InteractsWithPermissions;
use Kernel243\Artisan\Commands\Concerns\InteractsWithTenancy;

class RequestMakeCommand extends BaseCommand
{
    use InteractsWithFields;
    use InteractsWithPermissions;
    use InteractsWithTenancy;

    protected $signature = 'kernel:request {model : The model the requests validate (e.g. Post)}
                            {--fields= : Comma-separated fields, e.g. "title:string,email:email:unique,bio:text?"}
                            {--only= : Generate only the "store" or the "update" request}
                            {--tenant= : Tenant column (e.g. organization_id): unique and exists rules only see the current tenant}
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
            $tenancy = $this->tenancy($this->option('tenant') ?: null);
            // The tenant column is filled from the current tenant, never from the form.
            $fields = array_values(array_filter(
                $this->resolveFields($this->option('fields'), $modelClass),
                fn (array $field) => $field['name'] !== ($tenancy['column'] ?? null),
            ));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (['store', 'update'] as $kind) {
            if ($only === null || $only === $kind) {
                $target = $this->classTarget(Str::plural($model).'/'.Str::studly($kind).$model.'Request', 'Http/Requests', $module);
                $this->generate($target['path'], $this->buildRequest($target, $model, $fields, $kind, $tenancy), Str::studly($kind).' request');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{path: string, namespace: string, class: string}  $target
     * @param  list<array{name: string, type: string, nullable: bool, unique: bool}>  $fields
     * @param  array{column: string, trait: string, manager: string}|null  $tenancy
     */
    protected function buildRequest(array $target, string $model, array $fields, string $kind, ?array $tenancy = null): string
    {
        $table = Str::snake(Str::plural($model));
        // Route::resource('blog-posts') names its parameter blog_post.
        $routeParameter = $kind === 'update' ? Str::snake($model) : null;
        $permission = $this->permissionName($model, $kind === 'store' ? 'create' : 'update');
        $inController = (bool) $this->laravel['config']->get('kernel-artisan.requests.authorize_in_controller', true);

        $enums = [];

        $rules = array_map(function (array $field) use ($table, $routeParameter, $model, $tenancy, &$enums) {
            $enum = $this->enumClass($field, $model);

            if ($enum !== null) {
                $enums[] = $enum;
            }

            $tenantRelation = $tenancy !== null && $field['type'] === 'foreignid' && $this->isTenantModel($field, $tenancy);

            return "            '{$field['name']}' => ".$this->validationRules($field, $table, $routeParameter, $enum, $tenancy['column'] ?? null, $tenantRelation).',';
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

        if ($tenancy !== null && str_contains($content, 'TenantManager::class')) {
            $imports[] = $tenancy['manager'];
        }

        sort($imports);

        $content = str_replace(
            "use Illuminate\\Foundation\\Http\\FormRequest;\nuse Illuminate\\Validation\\Rule;\n",
            implode('', array_map(fn (string $import) => "use {$import};\n", $imports)),
            $content,
        );

        return $content;
    }

    /**
     * A related model isolated by the same tenant trait: its records are only valid within the tenant.
     *
     * @param  array{name: string}  $field
     * @param  array{trait: string}  $tenancy
     */
    protected function isTenantModel(array $field, array $tenancy): bool
    {
        $path = $this->laravel->path('Models/'.Str::studly(Str::beforeLast($field['name'], '_id')).'.php');

        return is_file($path) && str_contains((string) file_get_contents($path), "use {$tenancy['trait']};");
    }
}
