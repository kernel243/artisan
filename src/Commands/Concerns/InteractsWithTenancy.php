<?php

namespace Kernel243\Artisan\Commands\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\BaseCommand;

/**
 * Row-level tenancy shared by kernel:tenant, kernel:request and kernel:inertia-crud: a tenant column
 * (organization_id), its model (Organization), the BelongsToOrganization trait and the TenantManager.
 *
 * @phpstan-type Tenancy array{column: string, model: string, class: string, relation: string, trait: string, traitClass: string, manager: string, label: string, pluralLabel: string}
 *
 * @mixin BaseCommand
 */
trait InteractsWithTenancy
{
    /**
     * @return Tenancy|null
     */
    protected function tenancy(?string $column, ?string $model = null): ?array
    {
        if (blank($column) && blank($model)) {
            return null;
        }

        $column = blank($column) ? Str::snake((string) $model).'_id' : (string) $column;

        if (! preg_match('/^[a-z][a-z0-9_]*_id$/', $column)) {
            throw new InvalidArgumentException("Invalid tenant column \"{$column}\": use a foreign key such as organization_id.");
        }

        $model = blank($model) ? Str::studly(Str::beforeLast($column, '_id')) : Str::studly((string) $model);

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            throw new InvalidArgumentException("Invalid tenant model \"{$model}\".");
        }

        $root = $this->rootNamespace();

        return [
            'column' => $column,
            'model' => $model,
            'class' => $root.'\\Models\\'.$model,
            'relation' => Str::camel($model),
            'trait' => 'BelongsTo'.$model,
            'traitClass' => $root.'\\Models\\Concerns\\BelongsTo'.$model,
            'manager' => $root.'\\Tenancy\\TenantManager',
            'label' => Str::lower(Str::headline($model)),
            'pluralLabel' => Str::lower(Str::headline(Str::plural($model))),
        ];
    }

    /**
     * @param  Tenancy  $tenancy
     */
    protected function tenancyInstalled(array $tenancy): bool
    {
        return is_file($this->laravel->path('Models/Concerns/'.$tenancy['trait'].'.php'))
            && is_file($this->laravel->path('Tenancy/TenantManager.php'));
    }
}
