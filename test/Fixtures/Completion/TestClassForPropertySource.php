<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\Completion;

class TestClassForPropertySource
{
    public $publicProperty;
    public $anotherPublicProperty;
    protected $protectedProperty;
    private $privateProperty;

    public static $publicStaticProperty;
    protected static $protectedStaticProperty;
    private static $privateStaticProperty;
}
