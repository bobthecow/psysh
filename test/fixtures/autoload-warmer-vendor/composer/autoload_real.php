<?php

use Composer\Autoload\ClassLoader;

/*
 * Minimal Composer autoload_real.php fixture.
 */

class PsyTestComposerFixtureAutoloader
{
    private static $loader;

    public static function getLoader()
    {
        if (self::$loader !== null) {
            return self::$loader;
        }

        // Ensure the real Composer ClassLoader is available
        // It should already be loaded by the project's vendor/autoload.php
        if (!\class_exists(ClassLoader::class)) {
            throw new RuntimeException('Composer ClassLoader not available. Run tests via PHPUnit with Composer autoloader loaded.');
        }

        // Create a new instance for this fixture
        self::$loader = $loader = new ClassLoader();

        // Load the optimized classmap
        $classMap = require __DIR__.'/autoload_classmap.php';
        if ($classMap) {
            $loader->addClassMap($classMap);
        }

        // DO NOT register this loader globally - it's only for testing
        // $loader->register(true);

        return $loader;
    }
}
