<?php

namespace Kernel243\Artisan\Commands;

class View extends BaseCommand
{
    protected $signature = 'kernel:view {name : The view name in dot notation (e.g. admin.users.index)}
                            {--layout= : The layout the view extends (e.g. layouts.app)}
                            {--module= : The nwidart module where the view will be created}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new Blade view, optionally extending a layout';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/', $name)) {
            $this->error('Invalid view name. Use letters, digits, dashes and underscores separated by dots.');

            return self::FAILURE;
        }

        $module = $this->option('module') ?: null;

        if ($module && ! $this->moduleExists($module)) {
            $this->error("The module \"{$this->moduleName($module)}\" does not exist.");

            return self::FAILURE;
        }

        $path = $this->viewsDirectory($module).'/'.str_replace('.', '/', $name).'.blade.php';
        $layout = $this->option('layout');
        $content = $layout ? $this->render($this->getStubContent('view'), ['DummyLayout' => $layout]) : '';

        $this->generate($path, $content, 'View');

        return self::SUCCESS;
    }

    protected function viewsDirectory(?string $module): string
    {
        if (! $module) {
            return $this->laravel->resourcePath('views');
        }

        $base = $this->moduleBasePath($module);

        return is_dir($base.'/Resources/views') || $this->isLegacyModule($module)
            ? $base.'/Resources/views'
            : $base.'/resources/views';
    }
}
