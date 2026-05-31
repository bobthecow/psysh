<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Reflection;

use Psy\Reflection\ReflectionLanguageConstruct;
use Psy\Test\TestCase;

class ReflectionLanguageConstructTest extends TestCase
{
    /**
     * @dataProvider languageConstructs
     */
    public function testConstruction($keyword)
    {
        $refl = new ReflectionLanguageConstruct($keyword);
        $this->assertSame($keyword, $refl->getName());
        $this->assertSame($keyword, (string) $refl);
    }

    /**
     * @dataProvider languageConstructs
     */
    public function testKnownLanguageConstructs($keyword)
    {
        $this->assertTrue(ReflectionLanguageConstruct::isLanguageConstruct($keyword));
    }

    /**
     * @dataProvider languageConstructs
     */
    public function testFileName($keyword)
    {
        $refl = new ReflectionLanguageConstruct($keyword);
        $this->assertFalse($refl->getFileName());
    }

    /**
     * @dataProvider languageConstructs
     */
    public function testReturnsReference($keyword)
    {
        $refl = new ReflectionLanguageConstruct($keyword);
        $this->assertFalse($refl->returnsReference());
    }

    /**
     * @dataProvider languageConstructs
     */
    public function testGetParameters($keyword)
    {
        $refl = new ReflectionLanguageConstruct($keyword);
        $this->assertNotEmpty($refl->getParameters());
    }

    public function testArrayUsesManualParameterName()
    {
        $refl = new ReflectionLanguageConstruct('array');
        $params = $refl->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('values', $params[0]->getName());
        $this->assertTrue($params[0]->isVariadic());
    }

    /**
     * @dataProvider varAndVariadicVarsLanguageConstructs
     */
    public function testUsesVarAndVariadicVarsParameters(string $keyword)
    {
        $refl = new ReflectionLanguageConstruct($keyword);
        $params = $refl->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('var', $params[0]->getName());
        $this->assertFalse($params[0]->isVariadic());
        $this->assertSame('vars', $params[1]->getName());
        $this->assertTrue($params[1]->isVariadic());
    }

    public function testEchoUsesArgAndVariadicArgsParameters()
    {
        $refl = new ReflectionLanguageConstruct('echo');
        $params = $refl->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('arg1', $params[0]->getName());
        $this->assertFalse($params[0]->isVariadic());
        $this->assertSame('args', $params[1]->getName());
        $this->assertTrue($params[1]->isVariadic());
    }

    /**
     * @dataProvider languageConstructs
     */
    public function testExportThrows($keyword)
    {
        $this->expectException(\RuntimeException::class);
        ReflectionLanguageConstruct::export($keyword);

        $this->fail();
    }

    public static function languageConstructs()
    {
        return [
            ['isset'],
            ['unset'],
            ['empty'],
            ['echo'],
            ['print'],
            ['array'],
            ['list'],
            ['die'],
            ['exit'],
        ];
    }

    public static function varAndVariadicVarsLanguageConstructs()
    {
        return [
            ['isset'],
            ['unset'],
            ['list'],
        ];
    }

    /**
     * @dataProvider unknownLanguageConstructs
     */
    public function testUnknownLanguageConstructsThrowExceptions($keyword)
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReflectionLanguageConstruct($keyword);

        $this->fail();
    }

    public static function unknownLanguageConstructs()
    {
        return [
            ['async'],
            ['await'],
            ['comefrom'],
        ];
    }
}
