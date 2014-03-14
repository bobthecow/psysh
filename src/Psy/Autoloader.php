<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

/**
 * Psy class autoloader.
 */
class Autoloader
{
    /**
     * Register autoload() as an SPL autoloader.
     *
     * @see self::autoload
     */
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload Psy classes.
     *
     * @param string $class
     */
    public static function autoload($class)
    {
        if (0 !== strpos($class, 'Psy')) {
            return;
        }

        $file = dirname(__DIR__) . '/' . strtr($class, '\\', '/') . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}
