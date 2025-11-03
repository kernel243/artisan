<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;

class File extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:file {filename} {--ext= : The file extension. By default is php}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if ($this->isCorrectFilename($this->argument('filename'))) {
            $extension = $this->getExtension();
            $path = base_path(str_replace('.', '/', $this->argument('filename')).'.'.$extension);

            if ($this->shouldReplaceFile($path, 'There is already a file with this name do you want to replace it ? [y/n]')) {
                $filename = explode('.', $this->argument('filename'));
                $this->createFoldersIfNecessary($filename, base_path());
                file_put_contents($path, '');
                $this->info('File created successfully');
            }
            return self::SUCCESS;
        }
        $this->error('The filename is not correct.');
        return self::FAILURE;
    }

    /**
     * Get the file extension specified by the option.
     * PHP is considered as the default extension.
     *
     * @return string
     */
    protected function getExtension()
    {
        if ($this->hasOption('ext') && $this->option('ext') !== null)
            if (Str::startsWith($this->option('ext'), '.'))
                return Str::replaceFirst('.', '', $this->option('ext'));
            else
                return $this->option('ext');
        return 'php';
    }

    /**
     * Check if the filename is correct.
     *
     * @param $name
     * @return bool
     */
    protected function isCorrectFilename($name)
    {
        return (bool) preg_match('#^[a-zA-Z][a-zA-Z0-9._\-]+$#', $name);
    }
}
