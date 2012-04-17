<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

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
