<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\Concerns\InteractsWithTenancy;

/**
 * Row-level tenancy: a TenantManager (the current tenant, the authenticated user's by default), a
 * BelongsTo<Tenant> trait whose global scope fails closed, the tenant column on users, and the
 * scoped binding of the manager.
 */
class TenantMakeCommand extends BaseCommand
{
    use InteractsWithTenancy;

    protected $signature = 'kernel:tenant {model=Organization : The tenant model (e.g. Organization, Church)}
                            {--column= : The tenant column (default: <model>_id, e.g. organization_id)}
                            {--no-user-column : Do not add the tenant column to the users table}
                            {--force : Overwrite the files if they already exist}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create the row-level tenancy: TenantManager, a BelongsTo<Tenant> trait that fails closed, the users column';

    public function handle(): int
    {
        try {
            $tenancy = $this->tenancy($this->option('column') ?: null, (string) $this->argument('model'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_file($this->laravel->path('Models/'.$tenancy['model'].'.php'))) {
            $this->warn("The model \"{$tenancy['class']}\" does not exist yet: php artisan kernel:inertia-crud {$tenancy['model']} --fields=name:string");
        }

        $replacements = [
            'DummyColumn' => $tenancy['column'],
            'DummyLabel' => $tenancy['label'],
            'DummyPluralLabel' => $tenancy['pluralLabel'],
            'DummyTrait' => $tenancy['trait'],
        ];

        $manager = $this->laravel->path('Tenancy/TenantManager.php');

        if (! is_file($manager) || $this->isForced()) {
            $this->generate($manager, $this->render($this->getStubContent('tenant-manager'), [
                'DummyNamespace' => $this->rootNamespace().'\\Tenancy',
                ...$replacements,
            ]), 'Tenant manager');
        }

        $trait = $this->laravel->path('Models/Concerns/'.$tenancy['trait'].'.php');

        if (! is_file($trait) || $this->isForced()) {
            $imports = [
                $tenancy['class'],
                $tenancy['manager'],
                'Illuminate\\Database\\Eloquent\\Builder',
                'Illuminate\\Database\\Eloquent\\Model',
                'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            ];
            sort($imports);

            $this->generate($trait, $this->render($this->getStubContent('tenant-trait'), [
                'DummyNamespace' => $this->rootNamespace().'\\Models\\Concerns',
                'DummyImports' => implode("\n", array_map(fn (string $import) => "use {$import};", $imports)),
                'DummyTenant' => $tenancy['model'],
                'DummyRelation' => $tenancy['relation'],
                ...$replacements,
            ]), 'Tenant trait');
        }

        $this->bindManager($tenancy['manager']);

        if (! $this->option('no-user-column')) {
            $this->addUserColumn($tenancy);
        }

        $this->line("Isolate a model: use {$tenancy['trait']}; or php artisan kernel:inertia-crud Post --tenant={$tenancy['column']}");

        return self::SUCCESS;
    }

    /**
     * One current tenant per request: a scoped binding, reset between requests under Octane.
     */
    protected function bindManager(string $manager): void
    {
        $path = $this->laravel->path('Providers/AppServiceProvider.php');
        $relative = $this->relativePath($path);
        $binding = '$this->app->scoped(TenantManager::class);';

        if (! is_file($path)) {
            $this->warn("{$relative} not found: register the manager by hand: {$binding}");

            return;
        }

        $source = (string) file_get_contents($path);

        if (str_contains($source, 'TenantManager::class')) {
            return;
        }

        if (! preg_match('/public function register\(\): void\s*\{\n/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            $this->warn("The register() method of {$relative} is not recognized: add {$binding}");

            return;
        }

        $start = $matches[0][1] + strlen($matches[0][0]);
        // The empty register() of a new application only holds a "//" placeholder.
        $rest = (string) preg_replace('/^\s*\/\/\s*\n(?=\s*\})/', '', substr($source, $start), 1);
        $line = "        // Un seul tenant courant par requête, remis à zéro entre deux requêtes (Octane).\n        {$binding}\n";

        if (! preg_match('/^\s*\}/', $rest)) {
            $line .= "\n";
        }

        [$source] = $this->importClass(substr($source, 0, $start).$line.$rest, $manager);

        if ($this->isDryRun()) {
            $this->line("<comment>[dry-run]</comment> The manager would be registered in {$relative}: {$binding}");

            return;
        }

        file_put_contents($path, $source);
        static::$written[] = $path;
        $this->info("Tenant manager registered in {$relative}");
    }

    /**
     * @param  array{column: string, model: string, label: string}  $tenancy
     */
    protected function addUserColumn(array $tenancy): void
    {
        $directory = $this->laravel->databasePath('migrations');
        $usersTable = $this->usersTable();
        $tenantTable = Str::snake(Str::plural($tenancy['model']));

        foreach (glob($directory.'/*.php') ?: [] as $migration) {
            $source = (string) file_get_contents($migration);

            if (preg_match("/Schema::(create|table)\('{$usersTable}'/", $source) && str_contains($source, "'{$tenancy['column']}'")) {
                return;
            }
        }

        // The foreign key needs the tenants table first: its migration must come before.
        if ((glob($directory.'/*_create_'.$tenantTable.'_table.php') ?: []) === []) {
            $this->warn("No migration creates \"{$tenantTable}\" yet: create the {$tenancy['model']} model, then run kernel:tenant again to add {$usersTable}.{$tenancy['column']}.");

            return;
        }

        $this->generate($directory.'/'.date('Y_m_d_His').'_add_'.$tenancy['column'].'_to_'.$usersTable.'_table.php', $this->render($this->getStubContent('tenant-users-migration'), [
            'DummyUsersTable' => $usersTable,
            'DummyTenantTable' => $tenantTable,
            'DummyColumn' => $tenancy['column'],
            'DummyLabel' => $tenancy['label'],
        ]), 'Migration');
    }

    protected function usersTable(): string
    {
        $model = $this->laravel['config']->get('auth.providers.users.model');

        return is_string($model) && class_exists($model) ? (new $model)->getTable() : 'users';
    }
}
