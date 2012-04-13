<?php

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
