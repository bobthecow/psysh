<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\Util;

/**
 * Test fixture for magic method and property parsing.
 *
 * @method        string       getName()
 * @method        void         setName(string $name)
 * @method static self         find(int $id)
 * @method        Builder|self where(string $col, $val)
 *
 * @property       string $title
 * @property-read  int    $id
 * @property-write string $password
 */
class MagicClass
{
    public function __call($name, $args)
    {
        return null;
    }

    public function __get($name)
    {
        return null;
    }

    public static function __callStatic($name, $args)
    {
        return null;
    }
}
