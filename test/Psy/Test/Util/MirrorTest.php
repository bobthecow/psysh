<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Reflection\ReflectionConstant;
use Psy\Util\Mirror;

class MirrorTest extends \PHPUnit_Framework_TestCase
{
    const FOO           = 1;
    private $bar        = 2;
    private static $baz = 3;

    public function aPublicMethod()
    {
        // nada
    }

    public function testMirror()
    {
        $refl = Mirror::get('sort');
        $this->assertTrue($refl instanceof \ReflectionFunction);

        $refl = Mirror::get('Psy\Test\Util\MirrorTest');
        $this->assertTrue($refl instanceof \ReflectionClass);

        $refl = Mirror::get($this);
        $this->assertTrue($refl instanceof \ReflectionObject);

        $refl = Mirror::get($this, 'FOO');
        $this->assertTrue($refl instanceof ReflectionConstant);

        $refl = Mirror::get($this, 'bar');
        $this->assertTrue($refl instanceof \ReflectionProperty);

        $refl = Mirror::get($this, 'baz');
        $this->assertTrue($refl instanceof \ReflectionProperty);

        $refl = Mirror::get($this, 'aPublicMethod');
        $this->assertTrue($refl instanceof \ReflectionMethod);

        $refl = Mirror::get($this, 'baz', Mirror::STATIC_PROPERTY);
        $this->assertTrue($refl instanceof \ReflectionProperty);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMirrorThrowsExceptions()
    {
        Mirror::get($this, 'notAMethod');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider invalidArguments
     */
    public function testMirrorThrowsInvalidArgumentExceptions($value)
    {
        Mirror::get($value);
    }

    public function invalidArguments()
    {
        return array(
            array('not_a_function_or_class'),
            array(array()),
            array(1),
        );
    }
}
