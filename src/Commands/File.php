<?php

namespace Kernel243\Artisan\Commands;

class File extends BaseCommand
{
    protected $signature = 'kernel:file {filename : The file path in dot notation from the project root (e.g. docs.api.notes)}
                            {--ext=php : The file extension}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the file path without writing it}';

    protected $description = 'Create a new empty file';

    protected $aliases = ['make:file'];

    public function handle(): int
    {
        $filename = (string) $this->argument('filename');

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_\-]*(\.[A-Za-z0-9_\-]+)*$/', $filename)) {
            $this->error('The filename is not correct.');

            return self::FAILURE;
        }

        $extension = ltrim((string) ($this->option('ext') ?: 'php'), '.');
        $path = $this->laravel->basePath(str_replace('.', '/', $filename).'.'.$extension);

        $this->generate($path, '', 'File');

        return self::SUCCESS;
    }
}
