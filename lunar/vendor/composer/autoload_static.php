<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit15ac80d9eec6bcbbcbdcec5f1b390a9f
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Lunar\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Lunar\\' => 
        array (
            0 => __DIR__ . '/..' . '/lunar/payments-api-sdk/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit15ac80d9eec6bcbbcbdcec5f1b390a9f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit15ac80d9eec6bcbbcbdcec5f1b390a9f::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
