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

use Psy\CodeCleaner\FunctionReturnInWriteContextPass;

class FunctionReturnInWriteContextPassTest extends CodeCleanerTestCase
{
    /**
     * @before
     */
    public function getReady()
    {
        $this->setPass(new FunctionReturnInWriteContextPass());
    }

    /**
     * @dataProvider invalidStatements
     */
    public function testProcessStatementFails($code)
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('Can\'t use function return value in write context');

        $this->parseAndTraverse($code);

        $this->fail();
    }

    public function invalidStatements()
    {
        return [
            ['f(&g())'],
            ['[& $object->method()]'],
            ['$a->method(& $closure())'],
            ['[& A::b()]'],
            ['f() = 5'],
            ['unset(h())'],
        ];
    }

    public function testIsset()
    {
        $this->expectException(\Psy\Exception\FatalErrorException::class);
        $this->expectExceptionMessage('Cannot use isset() on the result of an expression (you can use "null !== expression" instead)');

        $this->traverser->traverse($this->parse('isset(strtolower("A"))'));

        $this->fail();
    }

    /**
     * @dataProvider validStatements
     */
    public function testValidStatements($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['isset($foo)'],
        ];
    }
}
