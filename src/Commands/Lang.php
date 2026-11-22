<?php

namespace Kernel243\Artisan\Commands;

class Lang extends BaseCommand
{
    protected $signature = 'kernel:lang {name? : The translation file name (not needed with --json)}
                            {--locale=en : The targeted locale}
                            {--json : Create a JSON translation file (lang/{locale}.json)}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new translation file';

    protected $aliases = ['make:lang'];

    public function handle(): int
    {
        $locale = (string) ($this->option('locale') ?: 'en');

        if (! preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]+)*$/', $locale)) {
            $this->error("Invalid locale \"{$locale}\".");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->generate($this->langPath($locale.'.json'), "{\n}\n", 'Translation file');

            return self::SUCCESS;
        }

        $name = (string) $this->argument('name');

        if ($name === '') {
            $this->error('No filename given. Use the --json flag to create a JSON translation file.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_\-]*$/', $name)) {
            $this->error('The given filename is not correct. Only letters, digits, dashes and underscores are allowed.');

            return self::FAILURE;
        }

        $this->generate($this->langPath($locale.'/'.$name.'.php'), $this->getStubContent('lang'), 'Translation file');

        return self::SUCCESS;
    }

    /**
     * Laravel 9+ uses lang/ at the project root; older apps may still use resources/lang.
     */
    protected function langPath(string $path): string
    {
        return rtrim($this->laravel->langPath(), '/').'/'.$path;
    }
}
