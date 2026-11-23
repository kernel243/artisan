<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithFields;
use Spatie\LaravelData\Data;

/**
 * Inertia + Vue TypeScript CRUD following the ELONGO CREA conventions:
 * migration, model, factory, DTO, form requests, actions, a thin controller,
 * named routes, Index / Form pages using Wayfinder, and Pest tests.
 *
 * @phpstan-import-type Field from InteractsWithFields
 */
class InertiaCrudMakeCommand extends BaseCommand
{
    use InteractsWithFields;

    /**
     * Fields typed as text in the list: too long for a table cell.
     */
    protected const LONG_TYPES = ['text', 'textarea', 'longtext', 'json', 'array', 'password'];

    protected $signature = 'kernel:inertia-crud {name : The model name (e.g. Post)}
                            {--fields= : Comma-separated fields, e.g. "title:string,email:email:unique,bio:text?,author_id:foreignId"}
                            {--zone= : Area of the application (e.g. admin): Http/Controllers/Admin, pages admin/..., routes admin.*}
                            {--no-tests : Do not generate the Pest tests}
                            {--no-format : Do not run Pint and Prettier on the generated files}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create an Inertia + Vue TypeScript CRUD (model, DTO, requests, actions, controller, routes, pages, tests)';

    /**
     * @var array{model: string, variable: string, plural: string, kebab: string, table: string, parameter: string, label: string}
     */
    protected array $names;

    /**
     * @var array{studly: string|null, kebab: string|null}
     */
    protected array $zone;

    /**
     * @var list<Field>
     */
    protected array $fields;

    public function handle(): int
    {
        try {
            [, $model] = $this->parseName((string) $this->argument('name'));
            $this->names = $this->names($model);
            $this->zone = $this->zone($this->option('zone') ?: $this->laravel['config']->get('kernel-artisan.inertia.zone'));
            $modelClass = $this->rootNamespace().'\\Models\\'.$model;
            $this->fields = $this->resolveFields($this->option('fields'), $this->option('fields') ? null : $modelClass);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        static::$written = [];

        if ($this->fields === []) {
            $this->warn('No field given (--fields) nor found on the model: the files contain placeholders to complete.');
        }

        if (! class_exists('Inertia\\Inertia')) {
            $this->warn('inertiajs/inertia-laravel is not installed: composer require inertiajs/inertia-laravel');
        }

        $this->generateMigration();
        $this->generateModel();
        $this->generateFactory();
        $this->callGenerators();
        $this->generateController();
        $this->generateRoutes();
        $this->generatePages();

        if (! $this->option('no-tests')) {
            $this->generateTest();
        }

        if (! $this->option('no-format') && ! $this->isDryRun()) {
            $this->format();
        }

        $this->info("Inertia CRUD for {$model} generated.");
        $this->line('Next: php artisan migrate && php artisan wayfinder:generate');

        return self::SUCCESS;
    }

    /**
     * @return array{model: string, variable: string, plural: string, kebab: string, table: string, parameter: string, label: string}
     */
    protected function names(string $model): array
    {
        return [
            'model' => $model,
            'variable' => Str::camel($model),
            'plural' => Str::camel(Str::plural($model)),
            'kebab' => Str::kebab(Str::plural($model)),
            'table' => Str::snake(Str::plural($model)),
            // Route::resource('blog-posts') names its parameter blog_post.
            'parameter' => Str::snake($model),
            'label' => Str::headline(Str::plural($model)),
        ];
    }

    /**
     * @return array{studly: string|null, kebab: string|null}
     */
    protected function zone(?string $zone): array
    {
        if (blank($zone)) {
            return ['studly' => null, 'kebab' => null];
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $zone)) {
            throw new InvalidArgumentException("Invalid zone \"{$zone}\": use a single word such as admin.");
        }

        return ['studly' => Str::studly($zone), 'kebab' => Str::kebab($zone)];
    }

    protected function routeName(string $action): string
    {
        return ($this->zone['kebab'] ? $this->zone['kebab'].'.' : '').$this->names['kebab'].'.'.$action;
    }

    /**
     * Folders of resources/js/pages follow the project: kebab-case (recent starter kits) or StudlyCase (Pages/).
     */
    protected function studlyPages(): bool
    {
        $case = $this->laravel['config']->get('kernel-artisan.inertia.pages_case');

        if ($case !== null) {
            return $case === 'studly';
        }

        return is_dir($this->laravel->resourcePath('js/Pages')) && ! is_dir($this->laravel->resourcePath('js/pages'));
    }

    /**
     * Inertia component name of a page: admin/posts/Index or Admin/Posts/Index.
     */
    protected function component(string $page): string
    {
        $segments = array_filter($this->studlyPages()
            ? [$this->zone['studly'], Str::studly($this->names['kebab'])]
            : [$this->zone['kebab'], $this->names['kebab']]);

        return implode('/', [...$segments, $page]);
    }

    // Back end

    protected function generateMigration(): void
    {
        $directory = $this->laravel->databasePath('migrations');
        $existing = glob($directory.'/*_create_'.$this->names['table'].'_table.php') ?: [];

        if ($existing !== []) {
            $this->line('A migration for "'.$this->names['table'].'" already exists ('.basename($existing[0]).'): skipped.');

            return;
        }

        $columns = array_map(fn (array $field) => '            '.$this->migrationColumn($field).';', $this->fields);

        $this->generate($directory.'/'.date('Y_m_d_His').'_create_'.$this->names['table'].'_table.php', $this->render($this->getStubContent('inertia.migration'), [
            'DummyTable' => $this->names['table'],
            'DummyLabel' => Str::lower($this->names['label']),
            'DummyColumns' => $columns === [] ? "            // \$table->string('name');" : implode("\n", $columns),
        ]), 'Migration');
    }

    /**
     * @param  Field  $field
     */
    protected function migrationColumn(array $field): string
    {
        $name = $field['name'];

        if ($field['type'] === 'foreignid') {
            return $field['nullable']
                ? "\$table->foreignId('{$name}')->nullable()->constrained()->nullOnDelete()"
                : "\$table->foreignId('{$name}')->constrained()->cascadeOnDelete()";
        }

        $column = match ($field['type']) {
            'text', 'textarea' => "text('{$name}')",
            'longtext' => "longText('{$name}')",
            'url' => "string('{$name}', 2048)",
            'phone' => "string('{$name}', 20)",
            'password' => "string('{$name}')",
            'integer', 'int' => "integer('{$name}')",
            'bigint' => "bigInteger('{$name}')",
            'decimal' => "decimal('{$name}', 15, 2)",
            'float' => "float('{$name}')",
            'double' => "double('{$name}')",
            'boolean', 'bool', 'tinyint' => "boolean('{$name}')->default(false)",
            'date' => "date('{$name}')",
            'datetime' => "dateTime('{$name}')",
            'timestamp' => "timestamp('{$name}')",
            'json', 'array' => "json('{$name}')",
            'uuid' => "uuid('{$name}')",
            'enum' => "string('{$name}', 20)",
            default => "string('{$name}')",
        };

        return '$table->'.$column.($field['nullable'] ? '->nullable()' : '').($field['unique'] ? '->unique()' : '');
    }

    protected function generateModel(): void
    {
        $model = $this->names['model'];
        $fillable = array_map(fn (array $field) => "        '{$field['name']}',", $this->fields);
        $hidden = array_map(fn (array $field) => "        '{$field['name']}',", array_filter($this->fields, fn (array $field) => $field['type'] === 'password'));

        $casts = [];

        foreach ($this->fields as $field) {
            $cast = match ($field['type']) {
                'integer', 'int', 'bigint' => 'integer',
                'decimal' => 'decimal:2',
                'float', 'double' => 'float',
                'boolean', 'bool', 'tinyint' => 'boolean',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                'json', 'array' => 'array',
                'password' => 'hashed',
                default => null,
            };

            if ($cast !== null) {
                $casts[] = "            '{$field['name']}' => '{$cast}',";
            }
        }

        $content = $this->render($this->getStubContent('inertia.model'), [
            'DummyNamespace' => $this->rootNamespace().'\\Models',
            'DummyFactory' => $model.'Factory',
            'DummyModel' => $model,
            'DummyFillable' => $fillable === [] ? "        // 'name'," : implode("\n", $fillable),
            'DummyHidden' => implode("\n", $hidden),
            'DummyCasts' => $casts === [] ? '            //' : implode("\n", $casts),
        ]);

        if ($hidden === []) {
            $content = preg_replace('/\n    \/\*\*\n     \* Les attributs masqués.*?protected \$hidden = \[\n\n    \];\n/s', '', $content);
        }

        $this->generate($this->laravel->path("Models/{$model}.php"), $content, 'Model');
    }

    protected function generateFactory(): void
    {
        $model = $this->names['model'];
        $definitions = array_map(fn (array $field) => "            '{$field['name']}' => ".$this->fakeValue($field).',', $this->fields);
        $imports = [$this->rootNamespace().'\\Models\\'.$model, 'Illuminate\\Database\\Eloquent\\Factories\\Factory'];

        foreach ($this->fields as $field) {
            if ($field['type'] === 'foreignid') {
                $imports[] = $this->relatedModel($field);
            }
        }

        $imports = array_values(array_unique($imports));
        sort($imports);

        $this->generate($this->laravel->databasePath("factories/{$model}Factory.php"), $this->render($this->getStubContent('inertia.factory'), [
            'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", $imports)),
            'DummyModel' => $model,
            'DummyDefinition' => $definitions === [] ? "            // 'name' => fake()->words(3, true)," : implode("\n", $definitions),
        ]), 'Factory');
    }

    /**
     * @param  Field  $field
     */
    protected function fakeValue(array $field): string
    {
        if ($field['type'] === 'foreignid') {
            return class_basename($this->relatedModel($field)).'::factory()';
        }

        $faker = $field['unique'] ? 'fake()->unique()' : 'fake()';

        return match ($field['type']) {
            'text', 'textarea', 'longtext' => "{$faker}->paragraph()",
            'email' => "{$faker}->safeEmail()",
            'password' => "'password'",
            'url' => "{$faker}->url()",
            'phone' => "'243'.{$faker}->numerify('#########')",
            'integer', 'int', 'bigint' => "{$faker}->numberBetween(1, 100)",
            'decimal', 'float', 'double' => "{$faker}->randomFloat(2, 1, 1000)",
            'boolean', 'bool', 'tinyint' => "{$faker}->boolean()",
            'date' => "{$faker}->date()",
            'datetime', 'timestamp' => "{$faker}->dateTime()",
            'json', 'array' => '[]',
            'uuid' => "{$faker}->uuid()",
            'enum' => "{$faker}->word()",
            default => "{$faker}->words(3, true)",
        };
    }

    /**
     * Model referenced by a foreign key: author_id → App\Models\Author.
     *
     * @param  Field  $field
     */
    protected function relatedModel(array $field): string
    {
        return $this->rootNamespace().'\\Models\\'.Str::studly(Str::beforeLast($field['name'], '_id'));
    }

    /**
     * DTO, form requests and actions come from their own generators.
     */
    protected function callGenerators(): void
    {
        $model = $this->names['model'];
        $common = array_filter(['--force' => $this->isForced(), '--dry-run' => $this->isDryRun()]);
        $fields = array_filter(['--fields' => $this->option('fields')]);

        if ($this->usesData()) {
            $this->call('kernel:data', ['name' => Str::plural($model).'/'.$model.'Data', ...$fields, ...$common]);
        }

        $this->call('kernel:request', ['model' => $model, ...$fields, ...$common]);

        foreach (['Create', 'Update', 'Delete'] as $verb) {
            $this->call('kernel:action', array_filter([
                'name' => $verb.$model,
                '--model' => $model,
                '--no-data' => ! $this->usesData(),
                ...$common,
            ]));
        }
    }

    protected function generateController(): void
    {
        $model = $this->names['model'];
        $variable = $this->names['variable'];
        $namespace = $this->rootNamespace().'\\Http\\Controllers'.($this->zone['studly'] ? '\\'.$this->zone['studly'] : '');
        $suffix = (string) $this->laravel['config']->get('kernel-artisan.actions.suffix', 'Action');
        $actionsNamespace = $this->rootNamespace().'\\Actions\\'.Str::plural($model);
        $requestsNamespace = $this->rootNamespace().'\\Http\\Requests\\'.Str::plural($model);
        $dataClass = $model.'Data';
        $useData = $this->usesData();

        $imports = [
            $this->rootNamespace().'\\Models\\'.$model,
            $requestsNamespace.'\\Store'.$model.'Request',
            $requestsNamespace.'\\Update'.$model.'Request',
            'Illuminate\\Http\\RedirectResponse',
            'Illuminate\\Http\\Request',
            'Inertia\\Inertia',
            'Inertia\\Response',
        ];

        foreach (['Create', 'Update', 'Delete'] as $verb) {
            $imports[] = $actionsNamespace.'\\'.$verb.$model.$suffix;
        }

        if ($useData) {
            $imports[] = $this->rootNamespace().'\\Data\\'.Str::plural($model).'\\'.$dataClass;
        }

        // The application base controller when it exists (imported from a zone namespace).
        if (! is_file($this->laravel->path('Http/Controllers/Controller.php'))) {
            $imports[] = 'Illuminate\\Routing\\Controller';
        } elseif ($this->zone['studly'] !== null) {
            $imports[] = $this->rootNamespace().'\\Http\\Controllers\\Controller';
        }

        $imports = array_values(array_unique($imports));
        sort($imports);

        $payload = $useData ? "{$dataClass}::from(\$request->validated())" : '$request->validated()';

        $this->generate($this->laravel->path('Http/Controllers/'.($this->zone['studly'] ? $this->zone['studly'].'/' : '')."{$model}Controller.php"), $this->render($this->getStubContent('inertia.controller'), [
            'DummyNamespace' => $namespace,
            'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", $imports)),
            'DummyClass' => $model.'Controller',
            'DummyModel' => $model,
            'DummyVariable' => $variable,
            'DummyPluralVariable' => $this->names['plural'],
            'DummySearch' => $this->searchClause(),
            'DummyPerPage' => (string) (int) $this->laravel['config']->get('kernel-artisan.inertia.per_page', 20),
            'DummyRowProps' => $this->propsArray($this->listFields(), 20),
            'DummyEditProps' => $this->propsArray($this->formFields(false), 16),
            'DummyIndexComponent' => $this->component('Index'),
            'DummyFormComponent' => $this->component('Form'),
            'DummyIndexRoute' => $this->routeName('index'),
            'DummyCreateCall' => $this->actionCall($payload),
            'DummyUpdateCall' => $this->actionCall("\${$variable}, {$payload}"),
            'DummyDeleteCall' => $this->actionCall("\${$variable}"),
            'DummyCreateAction' => 'Create'.$model.$suffix,
            'DummyUpdateAction' => 'Update'.$model.$suffix,
            'DummyDeleteAction' => 'Delete'.$model.$suffix,
            'DummyCreatedFlash' => $this->flash('Enregistrement créé avec succès.'),
            'DummyUpdatedFlash' => $this->flash('Enregistrement mis à jour.'),
            'DummyDeletedFlash' => $this->flash('Enregistrement supprimé.'),
        ]), 'Controller');
    }

    /**
     * Search on the first short text column.
     */
    protected function searchField(): ?string
    {
        foreach ($this->fields as $field) {
            if (in_array($field['type'], ['string', 'varchar', 'email', 'phone', 'url'], true)) {
                return $field['name'];
            }
        }

        return null;
    }

    protected function searchClause(): string
    {
        $field = $this->searchField();

        if ($field === null) {
            return '';
        }

        // whereLike() appeared in Laravel 11.17.
        $condition = method_exists(Builder::class, 'whereLike')
            ? "\$query->whereLike('{$field}', \"%{\$search}%\")"
            : "\$query->where('{$field}', 'like', \"%{\$search}%\")";

        return "\n                ->when(\$search !== '', fn (\$query) => {$condition})";
    }

    /**
     * Columns shown in the list.
     *
     * @return list<Field>
     */
    protected function listFields(): array
    {
        return array_slice(array_values(array_filter(
            $this->fields,
            fn (array $field) => ! in_array($field['type'], self::LONG_TYPES, true),
        )), 0, 5);
    }

    /**
     * Fields edited in the form (passwords are never sent back to the page).
     *
     * @return list<Field>
     */
    protected function formFields(bool $withPassword = true): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field) => ! in_array($field['type'], ['json', 'array'], true)
                && ($withPassword || $field['type'] !== 'password'),
        ));
    }

    /**
     * Props of a record, shaped explicitly: no raw model sent to the page.
     *
     * @param  list<Field>  $fields
     */
    protected function propsArray(array $fields, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $variable = $this->names['variable'];
        $lines = ["{$pad}'id' => \${$variable}->id,"];

        foreach ($fields as $field) {
            $value = match ($field['type']) {
                'date' => "\${$variable}->{$field['name']}?->toDateString()",
                // Format of <input type="datetime-local">.
                'datetime', 'timestamp' => "\${$variable}->{$field['name']}?->format('Y-m-d\\TH:i')",
                default => "\${$variable}->{$field['name']}",
            };

            $lines[] = "{$pad}'{$field['name']}' => {$value},";
        }

        return implode("\n", $lines);
    }

    /**
     * Call of the action injected as $action, with the method configured for the project.
     */
    protected function actionCall(string $arguments): string
    {
        $method = (string) $this->laravel['config']->get('kernel-artisan.actions.method', 'handle');

        return $method === '__invoke' ? "\$action({$arguments})" : "\$action->{$method}({$arguments})";
    }

    /**
     * Flash message, under the key the project shares through HandleInertiaRequests.
     */
    protected function flash(string $message): string
    {
        $key = (string) $this->laravel['config']->get('kernel-artisan.inertia.flash', 'success');

        return $key === 'toast'
            ? "->with('toast', ['type' => 'success', 'message' => '".addslashes($message)."'])"
            : "->with('{$key}', '".addslashes($message)."')";
    }

    /**
     * Without zone, or when routes/<zone>.php does not exist, the routes go to routes/web.php
     * in an auth group. routes/<zone>.php is expected to be mounted with its prefix and name.
     */
    protected function generateRoutes(): void
    {
        $zoneFile = $this->zone['kebab'] ? $this->laravel->basePath("routes/{$this->zone['kebab']}.php") : null;
        $inZoneFile = $zoneFile !== null && is_file($zoneFile);
        $routesPath = $inZoneFile ? $zoneFile : $this->laravel->basePath('routes/web.php');
        $file = 'routes/'.basename($routesPath);

        if (! is_file($routesPath)) {
            $this->warn("{$file} not found: routes not added.");

            return;
        }

        $controllerClass = $this->rootNamespace().'\\Http\\Controllers\\'.($this->zone['studly'] ? $this->zone['studly'].'\\' : '').$this->names['model'].'Controller';
        $routes = (string) file_get_contents($routesPath);

        foreach ([$controllerClass, '\\'.$controllerClass, class_basename($controllerClass)] as $reference) {
            if (str_contains($routes, "Route::resource('{$this->names['kebab']}', {$reference}::class")
                && ($reference !== class_basename($controllerClass) || str_contains($routes, "use {$controllerClass};"))) {
                $this->line("Routes for \"{$this->names['kebab']}\" already exist in {$file}: skipped.");

                return;
            }
        }

        [$routes, $controller] = $this->importInRoutes($routes, $controllerClass);
        $resource = "Route::resource('{$this->names['kebab']}', {$controller}::class)->except('show');";

        $group = match (true) {
            $inZoneFile => $resource,
            $this->zone['kebab'] !== null => "Route::middleware(['auth'])->prefix('{$this->zone['kebab']}')->name('{$this->zone['kebab']}.')->group(function () {\n    {$resource}\n});",
            default => "Route::middleware(['auth'])->group(function () {\n    {$resource}\n});",
        };

        $block = "// {$this->names['label']}\n{$group}\n";

        if ($this->isDryRun()) {
            $this->line("<comment>[dry-run]</comment> Routes would be added to {$file}:");
            $this->line($block);

            return;
        }

        file_put_contents($routesPath, rtrim($routes)."\n\n".$block);
        $this->info("Routes added to {$file}");
    }

    /**
     * Import the controller at the top of the routes file, in alphabetical order (as Pint does).
     * Falls back to the fully qualified name when another class already uses the short name.
     *
     * @return array{0: string, 1: string}
     */
    protected function importInRoutes(string $routes, string $class): array
    {
        $short = class_basename($class);

        if (str_contains($routes, "use {$class};")) {
            return [$routes, $short];
        }

        if (preg_match('/^use [^;]*(\\\\| as )'.preg_quote($short, '/').';$/m', $routes)) {
            return [$routes, '\\'.$class];
        }

        preg_match_all('/^use [^;]+;$/m', $routes, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return [preg_replace('/^<\?php\s*/', "<?php\n\nuse {$class};\n\n", $routes, 1), $short];
        }

        $first = $matches[0][0][1];
        $last = end($matches[0]);
        $end = $last[1] + strlen($last[0]);
        $block = substr($routes, $first, $end - $first);

        // Only reorder a block made of imports alone.
        if (preg_match('/^(use [^;]+;\n)*use [^;]+;$/', $block)) {
            $imports = [...explode("\n", $block), "use {$class};"];
            usort($imports, fn (string $a, string $b) => strcasecmp(rtrim($a, ';'), rtrim($b, ';')));

            return [substr($routes, 0, $first).implode("\n", $imports).substr($routes, $end), $short];
        }

        return [substr($routes, 0, $end)."\nuse {$class};".substr($routes, $end), $short];
    }

    // Front end

    protected function generatePages(): void
    {
        $directory = $this->laravel->resourcePath('js/'.($this->studlyPages() ? 'Pages' : 'pages').'/'.Str::beforeLast($this->component('Index'), '/'));
        $routesImport = '@/routes/'.($this->zone['kebab'] ? $this->zone['kebab'].'/' : '').$this->names['kebab'];
        $routesVariable = $this->names['variable'].'Routes';
        $layout = (string) $this->laravel['config']->get('kernel-artisan.inertia.layout', 'options');

        $replacements = [
            'DummyRoutesImport' => $routesImport,
            'DummyRoutes' => $routesVariable,
            'DummyModel' => $this->names['model'],
            'DummyVariable' => $this->names['variable'],
            'DummyPluralVariable' => $this->names['plural'],
            'DummyLabel' => $this->names['label'],
            'DummyLowerLabel' => Str::lower($this->names['label']),
        ];

        $index = $this->render($this->getStubContent('inertia.page.index'), $replacements + [
            'DummyRowType' => $this->tsType($this->listFields()),
            'DummyHeaders' => $this->indexHeaders(),
            'DummyCells' => $this->indexCells(),
            'DummyColspan' => (string) (count($this->listFields()) + 1),
            'DummySearchBlock' => $this->searchField() === null ? '' : $this->render($this->getStubContent('inertia.page.search'), $replacements),
        ]);

        $form = $this->render($this->getStubContent('inertia.page.form'), $replacements + [
            'DummyModelType' => $this->tsType($this->formFields(false)),
            'DummyFormDefaults' => $this->formDefaults(),
            'DummyInputs' => $this->formInputs(),
        ]);

        if ($this->searchField() === null) {
            $index = $this->withoutSearchScript($index);
        }

        $this->generate("{$directory}/Index.vue", $this->applyLayout($index, $layout), 'Index page');
        $this->generate("{$directory}/Form.vue", $this->applyLayout($form, $layout), 'Form page');
    }

    /**
     * Breadcrumbs through defineOptions (layout assigned in app.ts, recent starter kits),
     * or the page wrapped in <AppLayout> (older starter kits).
     */
    protected function applyLayout(string $page, string $layout): string
    {
        if ($layout === 'component') {
            $page = preg_replace('/defineOptions\(\{\n    layout: \{\n        breadcrumbs: (\[.*?\]),\n    \},\n\}\);/s', 'const breadcrumbs: BreadcrumbItem[] = $1;', $page);
            $page = str_replace('import { Head', "import AppLayout from '@/layouts/AppLayout.vue';\nimport type { BreadcrumbItem } from '@/types';\nimport { Head", $page);
            $page = preg_replace('/<template>\n(.*)\n<\/template>/s', "<template>\n    <AppLayout :breadcrumbs=\"breadcrumbs\">\n$1\n    </AppLayout>\n</template>", $page);
        }

        return $page;
    }

    protected function withoutSearchScript(string $index): string
    {
        return str_replace([
            "import { watchDebounced } from '@vueuse/core';\n",
            "import { ref } from 'vue';\n",
        ], '', preg_replace('/\nconst search = ref.*?\{ debounce: 300 \},\n\);\n/s', '', $index));
    }

    /**
     * @param  list<Field>  $fields
     */
    protected function tsType(array $fields): string
    {
        $lines = ['    id: number;'];

        foreach ($fields as $field) {
            $type = match ($field['type']) {
                'integer', 'int', 'bigint', 'foreignid', 'float', 'double' => 'number',
                'boolean', 'bool', 'tinyint' => 'boolean',
                // The decimal:2 cast serializes to a string.
                default => 'string',
            };

            $lines[] = "    {$field['name']}: {$type}".($field['nullable'] ? ' | null' : '').';';
        }

        return implode("\n", $lines);
    }

    protected function indexHeaders(): string
    {
        $headers = array_map(
            fn (array $field) => "                        <th class=\"px-4 py-3 font-medium\">{$this->fieldLabel($field['name'])}</th>",
            $this->listFields(),
        );

        return implode("\n", $headers);
    }

    protected function indexCells(): string
    {
        $variable = $this->names['variable'];

        return implode("\n", array_map(function (array $field) use ($variable) {
            $value = in_array($field['type'], ['boolean', 'bool', 'tinyint'], true)
                ? "{{ {$variable}.{$field['name']} ? 'Oui' : 'Non' }}"
                : "{{ {$variable}.{$field['name']} ?? '—' }}";

            $line = "                        <td class=\"px-4 py-3\">{$value}</td>";

            // Same wrapping as Prettier (printWidth 80).
            return mb_strlen($line) <= 80 ? $line : implode("\n", [
                '                        <td class="px-4 py-3">',
                "                            {$value}",
                '                        </td>',
            ]);
        }, $this->listFields()));
    }

    protected function formDefaults(): string
    {
        $variable = $this->names['variable'];

        $lines = array_map(function (array $field) use ($variable) {
            $default = match (true) {
                $field['type'] === 'password' => "''",
                in_array($field['type'], ['boolean', 'bool', 'tinyint'], true) => "props.{$variable}?.{$field['name']} ?? false",
                // An empty number field holds '', not null.
                in_array($field['type'], ['integer', 'int', 'bigint', 'foreignid', 'decimal', 'float', 'double'], true) => "props.{$variable}?.{$field['name']} ?? ('' as number | string)",
                default => "props.{$variable}?.{$field['name']} ?? ''",
            };

            return "    {$field['name']}: {$default},";
        }, $this->formFields());

        return $lines === [] ? '    // name: props.'.$variable."?.name ?? ''," : implode("\n", $lines);
    }

    protected function formInputs(): string
    {
        if ($this->formFields() === []) {
            return '            <!-- Ajoutez les champs du formulaire ici. -->';
        }

        return implode("\n\n", array_map(function (array $field) {
            $name = $field['name'];
            $label = $this->fieldLabel($name);
            $error = "                <InputError :message=\"form.errors.{$name}\" />";

            if (in_array($field['type'], ['boolean', 'bool', 'tinyint'], true)) {
                return implode("\n", [
                    '            <div class="grid gap-2">',
                    '                <div class="flex items-center gap-2">',
                    $this->element('input', ['id="'.$name.'"', 'v-model="form.'.$name.'"', 'type="checkbox"', 'class="size-4 rounded border-input"'], 20),
                    "                    <Label for=\"{$name}\">{$label}</Label>",
                    '                </div>',
                    $error,
                    '            </div>',
                ]);
            }

            if (in_array($field['type'], ['text', 'textarea', 'longtext'], true)) {
                $control = implode("\n", [
                    '                <textarea',
                    "                    id=\"{$name}\"",
                    "                    v-model=\"form.{$name}\"",
                    '                    rows="4"',
                    '                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"',
                    '                />',
                ]);
            } else {
                $attributes = match ($field['type']) {
                    'email' => 'type="email" autocomplete="email"',
                    'password' => 'type="password" autocomplete="new-password"',
                    'url' => 'type="url"',
                    'phone' => 'type="tel" autocomplete="tel"',
                    'integer', 'int', 'bigint', 'foreignid' => 'type="number" step="1"',
                    'decimal', 'float', 'double' => 'type="number" step="0.01"',
                    'date' => 'type="date"',
                    'datetime', 'timestamp' => 'type="datetime-local"',
                    default => 'type="text"',
                };

                $control = $this->element('Input', ['id="'.$name.'"', 'v-model="form.'.$name.'"', ...explode(' ', $attributes)], 16);
            }

            return implode("\n", [
                '            <div class="grid gap-2">',
                "                <Label for=\"{$name}\">{$label}</Label>",
                $control,
                $error,
                '            </div>',
            ]);
        }, $this->formFields()));
    }

    /**
     * Self-closing element on one line, or one attribute per line beyond 80 columns (as Prettier does).
     *
     * @param  list<string>  $attributes
     */
    protected function element(string $tag, array $attributes, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $line = "{$pad}<{$tag} ".implode(' ', $attributes).' />';

        if (mb_strlen($line) <= 80) {
            return $line;
        }

        return implode("\n", [
            "{$pad}<{$tag}",
            ...array_map(fn (string $attribute) => "{$pad}    {$attribute}", $attributes),
            "{$pad}/>",
        ]);
    }

    // Tests

    protected function generateTest(): void
    {
        $model = $this->names['model'];
        $variable = $this->names['variable'];
        $payloadFields = array_map(fn (array $field) => "'{$field['name']}'", array_filter(
            $this->formFields(),
            fn (array $field) => $field['type'] !== 'password',
        ));
        $passwords = array_map(fn (array $field) => "        '{$field['name']}' => 'motdepasse-solide',", array_filter(
            $this->fields,
            fn (array $field) => $field['type'] === 'password',
        ));

        $replacements = [
            'DummyModelClass' => $this->rootNamespace().'\\Models\\'.$model,
            'DummyModel' => $model,
            'DummyVariable' => $variable,
            'DummyTable' => $this->names['table'],
            'DummyLowerLabel' => Str::lower($this->names['label']),
            'DummyRoutePrefix' => Str::beforeLast($this->routeName('index'), '.'),
            'DummyIndexComponent' => $this->component('Index'),
            'DummyFormComponent' => $this->component('Form'),
            'DummyPayloadFields' => implode(', ', $payloadFields),
            'DummyPasswords' => implode("\n", $passwords),
            'DummyPluralVariable' => $this->names['plural'],
        ];

        $replacements['DummyEmptyTest'] = $this->hasRequiredField()
            ? $this->render($this->getStubContent('inertia.test.empty'), $replacements)
            : '';

        $path = $this->laravel->basePath('tests/Feature/'.($this->zone['studly'] ? $this->zone['studly'].'/' : '')."{$model}ControllerTest.php");

        $this->generate($path, $this->render($this->getStubContent('inertia.test'), $replacements), 'Test');
    }

    /**
     * An empty form is only rejected when a field is required.
     */
    protected function hasRequiredField(): bool
    {
        foreach ($this->fields as $field) {
            if (! $field['nullable'] && ! in_array($field['type'], ['boolean', 'bool', 'tinyint'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format the written files with the project's own tools and settings, when they are installed,
     * so that the generated code can be committed as is.
     */
    protected function format(): void
    {
        $files = array_values(array_unique(static::$written));
        $tools = [
            'Pint' => [$this->laravel->basePath('vendor/bin/pint'), fn (string $file) => str_ends_with($file, '.php')],
            'Prettier' => [$this->laravel->basePath('node_modules/.bin/prettier'), fn (string $file) => str_ends_with($file, '.vue')],
        ];

        foreach ($tools as $tool => [$binary, $filter]) {
            $targets = array_values(array_filter($files, $filter));

            if ($targets === [] || ! is_file($binary)) {
                continue;
            }

            $output = [];
            $command = escapeshellarg($binary).($tool === 'Prettier' ? ' --write' : '').' '.implode(' ', array_map('escapeshellarg', $targets)).' 2>&1';
            exec('cd '.escapeshellarg($this->laravel->basePath()).' && '.$command, $output, $code);

            $code === 0
                ? $this->line("Formatted with {$tool}: ".count($targets).' file(s).')
                : $this->warn("{$tool} failed:\n".implode("\n", $output));
        }
    }

    protected function usesData(): bool
    {
        return (bool) $this->laravel['config']->get('kernel-artisan.data.enabled', true) && class_exists(Data::class);
    }
}
