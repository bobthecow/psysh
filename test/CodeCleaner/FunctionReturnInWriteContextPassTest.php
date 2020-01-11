<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\FunctionReturnInWriteContextPass;

class FunctionReturnInWriteContextPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new FunctionReturnInWriteContextPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Can't use function return value in write context
     */
    public function testProcessStatementFails($code)
    {
        $this->parseAndTraverse($code);
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

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Cannot use isset() on the result of an expression (you can use "null !== expression" instead)
     */
    public function testIsset()
    {
        $this->traverser->traverse($this->parse('isset(strtolower("A"))'));
        $this->fail();
    }

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Can't use function return value in write context
     */
    public function testEmpty()
    {
        if (\version_compare(PHP_VERSION, '5.5', '>=')) {
            $this->markTestSkipped();
        }

        $this->traverser->traverse($this->parse('empty(strtolower("A"))'));
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
