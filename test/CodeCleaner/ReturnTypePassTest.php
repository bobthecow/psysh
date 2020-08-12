<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ReturnTypePass;

class ReturnTypePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        if (\version_compare(\PHP_VERSION, '7.1', '<')) {
            $this->markTestSkipped();
        }

        $this->setPass(new ReturnTypePass());
    }

    /**
     * @dataProvider missingReturns
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage A function with return type must return a value
     */
    public function testMissingReturn($code)
    {
        $this->parseAndTraverse($code);
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
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage (did you mean "return null;" instead of "return;"?)
     */
    public function testMissingNullableReturns($code)
    {
        $this->parseAndTraverse($code);
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
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage A void function must not return a value
     */
    public function testVoidReturns($code)
    {
        $this->parseAndTraverse($code);
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
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage (did you mean "return;" instead of "return null;"?)
     */
    public function testVoidNullReturns($code)
    {
        $this->parseAndTraverse($code);
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
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Void type cannot be nullable
     */
    public function testNullableVoidReturns($code)
    {
        $this->parseAndTraverse($code);
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
