<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3c1c06c108a20bd786c4ca17f0db037b
{
    public static $prefixLengthsPsr4 = array (
        'K' => 
        array (
            'Kernel243\\Artisan\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Kernel243\\Artisan\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3c1c06c108a20bd786c4ca17f0db037b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3c1c06c108a20bd786c4ca17f0db037b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3c1c06c108a20bd786c4ca17f0db037b::$classMap;

        }, null, ClassLoader::class);
    }
}