<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionConstant;

class ReflectionConstantTest extends \PHPUnit\Framework\TestCase
{
    const CONSTANT_ONE = 'one';

    public function testConstruction()
    {
        $refl  = new ReflectionConstant($this, 'CONSTANT_ONE');
        $class = $refl->getDeclaringClass();

        $this->assertInstanceOf('ReflectionClass', $class);
        $this->assertSame('Psy\Test\Reflection\ReflectionConstantTest', $class->getName());
        $this->assertSame('CONSTANT_ONE', $refl->getName());
        $this->assertSame('CONSTANT_ONE', (string) $refl);
        $this->assertSame('one', $refl->getValue());
        $this->assertNull($refl->getFileName());
        $this->assertFalse($refl->getDocComment());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUnknownConstantThrowsException()
    {
        new ReflectionConstant($this, 'UNKNOWN_CONSTANT');
    }

    /**
     * @expectedException \RuntimeException
     * @dataProvider notYetImplemented
     */
    public function testNotYetImplemented($method)
    {
        $refl = new ReflectionConstant($this, 'CONSTANT_ONE');
        $refl->$method();
    }

    public function notYetImplemented()
    {
        return [
            ['getStartLine'],
            ['getEndLine'],
            ['export'],
        ];
    }
}
