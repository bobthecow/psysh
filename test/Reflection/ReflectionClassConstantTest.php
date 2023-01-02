<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionClassConstant;

class ReflectionClassConstantTest extends \Psy\Test\TestCase
{
    const CONSTANT_ONE = 'one';

    public function testConstruction()
    {
        $refl = new ReflectionClassConstant($this, 'CONSTANT_ONE');
        $class = $refl->getDeclaringClass();

        $this->assertInstanceOf(\ReflectionClass::class, $class);
        $this->assertSame(self::class, $class->getName());
        $this->assertSame('CONSTANT_ONE', $refl->getName());
        $this->assertSame('CONSTANT_ONE', (string) $refl);
        $this->assertSame('one', $refl->getValue());
        $this->assertNull($refl->getFileName());
        $this->assertFalse($refl->getDocComment());
    }

    public function testUnknownConstantThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReflectionClassConstant($this, 'UNKNOWN_CONSTANT');

        $this->fail();
    }

    public function testExport()
    {
        $ret = ReflectionClassConstant::export($this, 'CONSTANT_ONE', true);
        $this->assertSame($ret, 'Constant [ public string CONSTANT_ONE ] { one }');
    }

    public function testExportOutput()
    {
        $this->expectOutputString("Constant [ public string CONSTANT_ONE ] { one }\n");
        ReflectionClassConstant::export($this, 'CONSTANT_ONE', false);
    }

    public function testModifiers()
    {
        $refl = new ReflectionClassConstant($this, 'CONSTANT_ONE');

        $this->assertSame(\ReflectionMethod::IS_PUBLIC, $refl->getModifiers());
        $this->assertFalse($refl->isPrivate());
        $this->assertFalse($refl->isProtected());
        $this->assertTrue($refl->isPublic());
    }

    /**
     * @dataProvider notYetImplemented
     */
    public function testNotYetImplemented($method)
    {
        $this->expectException(\RuntimeException::class);

        $refl = new ReflectionClassConstant($this, 'CONSTANT_ONE');
        $refl->$method();

        $this->fail();
    }

    public function notYetImplemented()
    {
        return [
            ['getStartLine'],
            ['getEndLine'],
        ];
    }
}
