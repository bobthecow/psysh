<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Reflection\ReflectionConstant;
use Psy\Reflection\ReflectionNamespace;
use Psy\Util\Mirror;

class MirrorTest extends \Psy\Test\TestCase
{
    const FOO = 1;
    private $bar = 2;
    private static $baz = 3;

    public function aPublicMethod()
    {
        // nada
    }

    public function testMirror()
    {
        $refl = Mirror::get('sort');
        $this->assertInstanceOf(\ReflectionFunction::class, $refl);

        $refl = Mirror::get(self::class);
        $this->assertInstanceOf(\ReflectionClass::class, $refl);

        $refl = Mirror::get($this);
        $this->assertInstanceOf(\ReflectionObject::class, $refl);

        $refl = Mirror::get($this, 'FOO');
        $this->assertInstanceOf(\ReflectionClassConstant::class, $refl);

        $refl = Mirror::get('PHP_VERSION');
        $this->assertInstanceOf(ReflectionConstant::class, $refl);

        $refl = Mirror::get($this, 'bar');
        $this->assertInstanceOf(\ReflectionProperty::class, $refl);

        $refl = Mirror::get($this, 'baz');
        $this->assertInstanceOf(\ReflectionProperty::class, $refl);

        $refl = Mirror::get($this, 'aPublicMethod');
        $this->assertInstanceOf(\ReflectionMethod::class, $refl);

        $refl = Mirror::get($this, 'baz', Mirror::STATIC_PROPERTY);
        $this->assertInstanceOf(\ReflectionProperty::class, $refl);

        $refl = Mirror::get('Psy\\Test\\Util');
        $this->assertInstanceOf(ReflectionNamespace::class, $refl);

        // This is both a namespace and a class, so let's make sure it gets the class:
        $refl = Mirror::get('Psy\\CodeCleaner');
        $this->assertInstanceOf(\ReflectionClass::class, $refl);
    }

    public function testMirrorThrowsExceptions()
    {
        $this->expectException(\RuntimeException::class);
        Mirror::get($this, 'notAMethod');

        $this->fail();
    }

    /**
     * @dataProvider invalidArguments
     */
    public function testMirrorThrowsInvalidArgumentExceptions($value)
    {
        $this->expectException(\InvalidArgumentException::class);
        Mirror::get($value);

        $this->fail();
    }

    public function invalidArguments()
    {
        return [
            ['not_a_function_or_class'],
            [[]],
            [1],
        ];
    }
}
