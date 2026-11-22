<?php

namespace Kernel243\Artisan\Commands;

use Illuminate\Support\Str;
use InvalidArgumentException;

class ClassMakeCommand extends BaseCommand
{
    protected $signature = 'kernel:class {name : The class path from the project root (e.g. App/Handlers/UserHandler)}
                            {--kind=class : The kind of file to create (class, trait, interface or enum)}
                            {--separator= : Extra character used to separate folders (e.g. ".")}
                            {--force : Overwrite the file if it already exists}
                            {--dry-run : Display the generated file without writing it}';

    protected $description = 'Create a new class, trait, interface or enum anywhere in the project';

    protected const KINDS = ['class', 'trait', 'interface', 'enum'];

    public function handle(): int
    {
        $kind = strtolower((string) ($this->option('kind') ?: 'class'));

        if (! in_array($kind, self::KINDS, true)) {
            $this->error('Invalid kind. The kind must be one of: '.implode(', ', self::KINDS).'.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');

        if ($separator = $this->option('separator')) {
            $name = str_replace($separator, '/', $name);
        }

        try {
            [$namespace, $class] = $this->parseName($name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = $this->pathFor($namespace, $class);

        $content = $this->render($this->getStubContent($kind), [
            "DummyNamespace\n\n" => $namespace === '' ? '' : "namespace {$namespace};\n\n",
            'DummyClass' => $class,
            'DummyTrait' => $class,
            'DummyInterface' => $class,
            'DummyEnum' => $class,
        ]);

        $this->generate($path, $content, Str::ucfirst($kind));

        return self::SUCCESS;
    }

    /**
     * Map the root namespace to app/ (App\Foo => app/Foo.php), other namespaces to their folders.
     */
    protected function pathFor(string $namespace, string $class): string
    {
        $root = $this->rootNamespace();

        if ($namespace === $root || Str::startsWith($namespace, $root.'\\')) {
            $relative = trim(Str::after($namespace, $root), '\\');

            return $this->laravel->path(($relative === '' ? '' : str_replace('\\', '/', $relative).'/').$class.'.php');
        }

        $relative = $namespace === '' ? '' : str_replace('\\', '/', $namespace).'/';

        return $this->laravel->basePath($relative.$class.'.php');
    }
}
