<?php

namespace Kernel243\Artisan\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kernel243\Artisan\Commands\BaseCommand;
use Throwable;

/**
 * Field definitions shared by the generators.
 *
 * Syntax of --fields: "name:type[:modifier...]" separated by commas, e.g.
 * "title:string,email:email:unique,bio:text:nullable,price:decimal,author_id:foreignId".
 * A trailing "?" on the type is a shortcut for "nullable" (bio:text?).
 *
 * @phpstan-type Field array{name: string, type: string, nullable: bool, unique: bool}
 *
 * @mixin BaseCommand
 */
trait InteractsWithFields
{
    protected const FIELD_TYPES = [
        'string', 'varchar', 'text', 'textarea', 'longtext', 'email', 'password', 'url', 'phone',
        'integer', 'int', 'bigint', 'foreignid', 'decimal', 'float', 'double',
        'boolean', 'bool', 'tinyint', 'date', 'datetime', 'timestamp', 'json', 'array', 'uuid', 'enum',
    ];

    /**
     * @return list<Field>
     */
    protected function parseFields(?string $fields): array
    {
        if (blank($fields)) {
            return [];
        }

        $parsed = [];

        foreach (explode(',', $fields) as $definition) {
            $parts = array_values(array_filter(array_map('trim', explode(':', $definition)), fn ($part) => $part !== ''));

            if ($parts === []) {
                continue;
            }

            $name = array_shift($parts);

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Invalid field name \"{$name}\".");
            }

            $type = strtolower(array_shift($parts) ?? 'string');
            $modifiers = array_map('strtolower', $parts);

            if (str_ends_with($type, '?')) {
                $type = rtrim($type, '?');
                $modifiers[] = 'nullable';
            }

            if (! in_array($type, self::FIELD_TYPES, true)) {
                throw new InvalidArgumentException("Unknown type \"{$type}\" for the field \"{$name}\". Allowed types: ".implode(', ', self::FIELD_TYPES).'.');
            }

            $parsed[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => in_array('nullable', $modifiers, true),
                'unique' => in_array('unique', $modifiers, true),
            ];
        }

        return $parsed;
    }

    /**
     * Use --fields when given, otherwise read the fillable attributes and casts of an existing model.
     *
     * @return list<Field>
     */
    protected function resolveFields(?string $fields, ?string $modelClass): array
    {
        if (filled($fields) || $modelClass === null) {
            return $this->parseFields($fields);
        }

        return $this->fieldsFromModel($modelClass);
    }

    /**
     * @return list<Field>
     */
    protected function fieldsFromModel(string $modelClass): array
    {
        try {
            if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                return [];
            }

            /** @var Model $model */
            $model = new $modelClass;
            $casts = $model->getCasts();
        } catch (Throwable) {
            return [];
        }

        return array_values(array_map(function (string $attribute) use ($casts) {
            $cast = strtolower((string) ($casts[$attribute] ?? ''));

            $type = match (true) {
                str_ends_with($attribute, '_id') => 'foreignid',
                $attribute === 'email' => 'email',
                str_starts_with($cast, 'decimal') || in_array($cast, ['float', 'double', 'real'], true) => 'decimal',
                in_array($cast, ['int', 'integer'], true) => 'integer',
                in_array($cast, ['bool', 'boolean'], true) => 'boolean',
                str_starts_with($cast, 'datetime') || str_starts_with($cast, 'immutable_datetime') || $cast === 'timestamp' => 'datetime',
                str_starts_with($cast, 'date') || str_starts_with($cast, 'immutable_date') => 'date',
                in_array($cast, ['array', 'json', 'collection', 'object'], true) => 'json',
                $cast === 'hashed' => 'password',
                enum_exists($casts[$attribute] ?? '') => 'enum',
                default => 'string',
            };

            return ['name' => $attribute, 'type' => $type, 'nullable' => false, 'unique' => false];
        }, $model->getFillable()));
    }

    /**
     * PHP type of a field in a DTO.
     *
     * Dates stay strings: forms send "2026-11-23" or "2026-11-23T10:00", which the
     * DATE_ATOM format of laravel-data rejects, and the model cast converts them anyway.
     *
     * @param  Field  $field
     */
    protected function phpType(array $field): string
    {
        return match ($field['type']) {
            'integer', 'int', 'bigint', 'foreignid' => 'int',
            'decimal', 'float', 'double' => 'float',
            'boolean', 'bool', 'tinyint' => 'bool',
            'json', 'array' => 'array',
            default => 'string',
        };
    }

    /**
     * Validation rules of a field, as PHP source code.
     *
     * @param  Field  $field
     */
    protected function validationRules(array $field, string $table, ?string $ignoreRouteParameter = null): string
    {
        $presence = $field['nullable'] ? "'nullable'" : (in_array($field['type'], ['boolean', 'bool', 'tinyint'], true) ? "'sometimes'" : "'required'");

        $rules = match ($field['type']) {
            'string', 'varchar', 'phone' => ["'string'", "'max:255'"],
            'text', 'textarea', 'longtext' => ["'string'"],
            'email' => ["'string'", "'email'", "'max:255'"],
            'password' => ["'string'", "'min:8'"],
            'url' => ["'url'", "'max:2048'"],
            'uuid' => ["'uuid'"],
            'integer', 'int', 'bigint' => ["'integer'"],
            'foreignid' => ["'integer'", "Rule::exists('".Str::plural(Str::beforeLast($field['name'], '_id'))."', 'id')"],
            'decimal', 'float', 'double' => ["'numeric'", "'min:0'"],
            'boolean', 'bool', 'tinyint' => ["'boolean'"],
            'date' => ["'date'"],
            'datetime', 'timestamp' => ["'date'"],
            'json', 'array' => ["'array'"],
            'enum' => ["'string'"],
            default => ["'string'"],
        };

        if ($field['unique']) {
            $rules[] = $ignoreRouteParameter
                ? "Rule::unique('{$table}', '{$field['name']}')->ignore(\$this->route('{$ignoreRouteParameter}'))"
                : "Rule::unique('{$table}', '{$field['name']}')";
        }

        return '['.implode(', ', [$presence, ...$rules]).']';
    }

    /**
     * Human label of a field, used as validation attribute.
     */
    protected function fieldLabel(string $field): string
    {
        return Str::ucfirst(str_replace('_', ' ', Str::beforeLast($field, '_id') ?: $field));
    }
}
