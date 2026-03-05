<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures;

class ClassWithStaticMagicMethods
{
    public static function publicStaticMethod()
    {
    }

    protected static function protectedStaticMethod()
    {
    }

    private static function privateStaticMethod()
    {
    }

    public function instanceMethod()
    {
    }

    public static function __callStatic($name, $arguments)
    {
    }
}
