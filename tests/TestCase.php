<?php

namespace Kernel243\Artisan\Tests;

use Illuminate\Filesystem\Filesystem;
use Kernel243\Artisan\CommandServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $sandbox;

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir().'/kernel243-artisan-'.bin2hex(random_bytes(6));

        $files = new Filesystem;
        $skeleton = __DIR__.'/../vendor/orchestra/testbench-core/laravel';

        // The skeleton's vendor/ is a symlink to the package vendor/: link it instead of copying it.
        foreach ($files->directories($skeleton) as $directory) {
            if (basename($directory) !== 'vendor') {
                $files->copyDirectory($directory, $this->sandbox.'/'.basename($directory));
            }
        }

        foreach ($files->files($skeleton, true) as $file) {
            $files->copy($file->getPathname(), $this->sandbox.'/'.$file->getFilename());
        }

        symlink(realpath(__DIR__.'/../vendor'), $this->sandbox.'/vendor');

        // Without its bootstrap file, Testbench builds a plain application on the sandbox.
        $files->delete($this->sandbox.'/bootstrap/app.php');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        @unlink($this->sandbox.'/vendor');
        (new Filesystem)->deleteDirectory($this->sandbox);
    }

    /**
     * Each test runs against its own copy of the Laravel skeleton (Testbench 9+).
     */
    protected function getApplicationBasePath()
    {
        return $this->sandbox;
    }

    /**
     * Same as above for Testbench 8 (Laravel 10).
     */
    protected function getBasePath()
    {
        return $this->sandbox;
    }

    protected function getPackageProviders($app)
    {
        return array_filter([
            CommandServiceProvider::class,
            // Lets the tests instantiate the generated DTOs.
            class_exists(LaravelDataServiceProvider::class) ? LaravelDataServiceProvider::class : null,
        ]);
    }

    protected function sandboxPath(string $path = ''): string
    {
        return $this->sandbox.($path === '' ? '' : '/'.$path);
    }

    protected function writeFixture(string $path, string $content): void
    {
        (new Filesystem)->ensureDirectoryExists(dirname($this->sandboxPath($path)));
        file_put_contents($this->sandboxPath($path), $content);
    }

    protected function makeModel(string $class = 'Post', string $directory = 'app/Models', string $namespace = 'App\\Models'): void
    {
        $this->writeFixture("{$directory}/{$class}.php", "<?php\n\nnamespace {$namespace};\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$class} extends Model\n{\n}\n");
    }

    /**
     * Assert a generated Blade view compiles to valid PHP.
     */
    protected function assertValidBlade(string $path): void
    {
        $this->assertFileExists($this->sandboxPath($path));
        $compiled = $this->sandboxPath('storage/compiled-'.md5($path).'.php');
        file_put_contents($compiled, app('blade.compiler')->compileString(file_get_contents($this->sandboxPath($path))));
        exec('php -l '.escapeshellarg($compiled).' 2>&1', $output, $code);
        $this->assertSame(0, $code, $path."\n".implode("\n", $output));
    }

    /**
     * Assert a generated PHP file has a valid syntax.
     */
    protected function assertValidPhp(string $path): void
    {
        $this->assertFileExists($this->sandboxPath($path));
        exec('php -l '.escapeshellarg($this->sandboxPath($path)).' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }
}
