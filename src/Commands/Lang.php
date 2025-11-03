<?php

namespace Kernel243\Artisan\Commands;

class Lang extends BaseCommand
{
    protected $signature = 'make:lang {name?} 
                            {--locale= : The targeted locale (default: en)}
                            {--json : Create a JSON language file}
                            {--force : Overwrite existing files without confirmation}
                            {--dry-run : Preview the file that would be created without actually creating it}';

    protected $description = 'Create a new language file';

    public function handle(): int
    {
        $name = $this->hasArgument('name') ? $this->argument('name') : '';
        $locale = $this->option('locale') ?? 'en';

        if ($this->option('json')) {
            return $this->createJson($locale);
        }

        if (empty($name)) {
            $this->error('No filename is given. Use --json flag for JSON language files.');
            return self::FAILURE;
        }

        if (!$this->nameIsCorrect($name)) {
            $this->error('The given filename is not correct. Only alphanumeric characters are allowed.');
            return self::FAILURE;
        }

        return $this->createLang($name, $locale);
    }

    protected function createLang(string $name, string $locale): int
    {
        try {
            $path = $this->getLangPath($locale, $name . '.php');
            $question = "There is already a locale file with this name. Do you want to replace it? [y/n]";

            if (!$this->shouldReplaceFile($path, $question)) {
                return self::SUCCESS;
            }

            $this->ensureLocaleDirectoryExists($locale);

            $stub = $this->getStubContent('lang');
            $this->writeFile($path, $stub);

            $this->displaySuccess('Language file created successfully!', $path);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function createJson(string $locale): int
    {
        try {
            $path = $this->getLangPath($locale . '.json');
            $question = "There is already a locale file with this name. Do you want to replace it? [y/n]";

            if (!$this->shouldReplaceFile($path, $question)) {
                return self::SUCCESS;
            }

            $content = "{\n    \n}";
            $this->writeFile($path, $content);

            $this->displaySuccess('Language file created successfully!', $path);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function getLangPath(string ...$parts): string
    {
        return resource_path('lang' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
    }

    protected function ensureLocaleDirectoryExists(string $locale): void
    {
        $directory = resource_path('lang' . DIRECTORY_SEPARATOR . $locale);

        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Unable to create directory: {$directory}");
            }
        }
    }

    protected function nameIsCorrect(string $name): bool
    {
        return (bool) preg_match('#^[a-zA-Z][a-zA-Z0-9]+$#', $name);
    }
}
