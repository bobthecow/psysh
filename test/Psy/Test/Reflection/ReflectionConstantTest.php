<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionConstant;

class ReflectionConstantTest extends \PHPUnit_Framework_TestCase
{
    const CONSTANT_ONE = 'one';

    public function testConstruction()
    {
        $refl  = new ReflectionConstant($this, 'CONSTANT_ONE');
        $class = $refl->getDeclaringClass();

        $this->assertTrue($class instanceof \ReflectionClass);
        $this->assertEquals('Psy\Test\Reflection\ReflectionConstantTest', $class->getName());
        $this->assertEquals('CONSTANT_ONE', $refl->getName());
        $this->assertEquals('CONSTANT_ONE', (string) $refl);
        $this->assertEquals('one', $refl->getValue());
        $this->assertEquals(null, $refl->getFileName());
        $this->assertFalse($refl->getDocComment());
    }

    /**
     * @expectedException InvalidArgumentException
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
        return array(
            array('getStartLine'),
            array('getEndLine'),
            array('export'),
        );
    }
}
