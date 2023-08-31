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

use Psy\Reflection\ReflectionConstant;

const SOME_CONSTANT = 'yep';

class ReflectionConstantTest extends \Psy\Test\TestCase
{
    public function testConstruction()
    {
        $refl = new ReflectionConstant('Psy\\Test\\Reflection\\SOME_CONSTANT');

        $this->assertFalse($refl->getDocComment());
        $this->assertSame('Psy\\Test\\Reflection\\SOME_CONSTANT', $refl->getName());
        $this->assertSame('Psy\\Test\\Reflection', $refl->getNamespaceName());
        $this->assertSame('yep', $refl->getValue());
        $this->assertTrue($refl->inNamespace());
        $this->assertSame('Psy\\Test\\Reflection\\SOME_CONSTANT', (string) $refl);
        $this->assertNull($refl->getFileName());
    }

    public function testBuiltInConstant()
    {
        $refl = new ReflectionConstant('PHP_VERSION');

        $this->assertSame('PHP_VERSION', $refl->getName());
        $this->assertSame('PHP_VERSION', (string) $refl);
        $this->assertSame(\PHP_VERSION, $refl->getValue());
        $this->assertFalse($refl->inNamespace());
        $this->assertSame('', $refl->getNamespaceName());
    }

    /**
     * @dataProvider magicConstants
     */
    public function testIsMagicConstant($name, $is)
    {
        $this->assertSame($is, ReflectionConstant::isMagicConstant($name));
    }

    public function magicConstants()
    {
        return [
            ['__LINE__', true],
            ['__FILE__', true],
            ['__DIR__', true],
            ['__FUNCTION__', true],
            ['__CLASS__', true],
            ['__TRAIT__', true],
            ['__METHOD__', true],
            ['__NAMESPACE__', true],
            ['__COMPILER_HALT_OFFSET__', true],
            ['PHP_VERSION', false],
            ['PHP_EOL', false],
            ['Psy\\Test\\Reflection\\SOME_CONSTANT', false],
            ['What if it isn\'t even a valid constant name?', false],
        ];
    }

    public function testUnknownConstantThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReflectionConstant('UNKNOWN_CONSTANT');

        $this->fail();
    }

    public function testExport()
    {
        $ret = ReflectionConstant::export('Psy\\Test\\Reflection\\SOME_CONSTANT', true);
        $this->assertSame($ret, 'Constant [ string Psy\\Test\\Reflection\\SOME_CONSTANT ] { yep }');
    }

    public function testExportOutput()
    {
        $this->expectOutputString("Constant [ string Psy\\Test\\Reflection\\SOME_CONSTANT ] { yep }\n");
        ReflectionConstant::export('Psy\\Test\\Reflection\\SOME_CONSTANT', false);
    }

    public function testGetFileName()
    {
        $refl = new ReflectionConstant('Psy\\Test\\Reflection\\SOME_CONSTANT');
        $this->assertNull($refl->getFileName());
    }

    /**
     * @dataProvider notYetImplemented
     */
    public function testNotYetImplemented($method)
    {
        $this->expectException(\RuntimeException::class);

        $refl = new ReflectionConstant('Psy\\Test\\Reflection\\SOME_CONSTANT');
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
