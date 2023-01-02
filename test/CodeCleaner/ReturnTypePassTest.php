<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ReturnTypePass;

class ReturnTypePassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        if (\version_compare(\PHP_VERSION, '7.1', '<')) {
            $this->markTestSkipped();
        }

        $this->setPass(new ReturnTypePass());
    }

    /**
     * @dataProvider missingReturns
     */
    public function testMissingReturn($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('A function with return type must return a value');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function missingReturns()
    {
        return [
            ['function foo(): int { return; }'],
            ['$bar = function(): int { return; }'],
            ['function baz() { $qux = function(): int { return; }; }'],
        ];
    }

    /**
     * @dataProvider missingNullableReturns
     */
    public function testMissingNullableReturns($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('(did you mean "return null;" instead of "return;"?)');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function missingNullableReturns()
    {
        return [
            ['function foo(): ?int { return; }'],
            ['$bar = function(): ?int { return; }'],
            ['function baz() { $qux = function(): ?int { return; }; }'],
        ];
    }

    /**
     * @dataProvider voidReturns
     */
    public function testVoidReturns($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('A void function must not return a value');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function voidReturns()
    {
        return [
            ['function foo(): void { return 1; }'],
            ['$bar = function(): void { return "bar"; }'],
            ['function baz() { $qux = function(): void { return []; }; }'],
        ];
    }

    /**
     * @dataProvider voidNullReturns
     */
    public function testVoidNullReturns($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('(did you mean "return;" instead of "return null;"?)');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function voidNullReturns()
    {
        return [
            ['function foo(): void { return null; }'],
            ['$bar = function(): void { return NULL; }'],
            ['function baz() { $qux = function(): void { return null; }; }'],
        ];
    }

    /**
     * @dataProvider nullableVoidReturns
     */
    public function testNullableVoidReturns($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('Void type cannot be nullable');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function nullableVoidReturns()
    {
        return [
            ['function foo(): ?void { return null; }'],
            ['$bar = function(): ?void { return NULL; }'],
            ['function baz() { $qux = function(): ?void { return null; }; }'],
        ];
    }
}
