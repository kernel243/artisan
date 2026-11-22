<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;

class EnumMakeCommand extends BaseCommand
{
    protected $signature = 'kernel:enum {name : The enum name (e.g. PaymentStatus)}
                            {--cases= : Comma-separated values, e.g. "pending,paid,failed"}
                            {--module= : The nwidart module where the enum will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated files without writing them}';

    protected $description = 'Create a string-backed enum with label() and the HasOptions trait (values(), options())';

    public function handle(): int
    {
        try {
            $module = $this->option('module') ?: null;
            $target = $this->classTarget($this->argument('name'), 'Enums', $module);
            $values = $this->parseCases((string) $this->option('cases'));
            $concerns = $this->classTarget('HasOptions', 'Enums/Concerns', $module);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_file($concerns['path'])) {
            $this->generate($concerns['path'], $this->render($this->getStubContent('enum-has-options'), [
                'DummyNamespace' => $concerns['namespace'],
            ]), 'HasOptions trait');
        }

        $cases = $values === []
            ? "    // case Pending = 'pending';"
            : implode("\n", array_map(fn (string $value) => '    case '.Str::studly($value)." = '{$value}';", $values));

        $labels = $values === []
            ? '            // self::Pending => \'En attente\','
            : implode("\n", array_map(fn (string $value) => '            self::'.Str::studly($value)." => '".str_replace("'", "\\'", Str::ucfirst(str_replace('_', ' ', $value)))."',", $values));

        $content = $this->render($this->getStubContent('backed-enum'), [
            'DummyNamespace' => $target['namespace'],
            'DummyClass' => $target['class'],
            'DummyHasOptionsClass' => $concerns['namespace'].'\\HasOptions',
            'DummyCases' => $cases,
            'DummyLabels' => $labels,
        ]);

        // A match without arm does not compile: keep the generated enum valid until cases are added.
        if ($values === []) {
            $content = str_replace("        return match (\$this) {\n            // self::Pending => 'En attente',\n        };", "        // return match (\$this) {\n        //     self::Pending => 'En attente',\n        // };\n\n        return Str::headline(\$this->name);", $content);
            $content = str_replace("use {$concerns['namespace']}\\HasOptions;", "use {$concerns['namespace']}\\HasOptions;\nuse Illuminate\\Support\\Str;", $content);
        }

        $this->generate($target['path'], $content, 'Enum');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function parseCases(string $cases): array
    {
        $values = array_values(array_unique(array_filter(array_map(
            fn (string $value) => Str::snake(trim($value)),
            explode(',', $cases),
        ))));

        foreach ($values as $value) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
                throw new InvalidArgumentException("Invalid enum value \"{$value}\".");
            }
        }

        return $values;
    }
}
