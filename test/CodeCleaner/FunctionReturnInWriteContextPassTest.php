<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\NodeTraverser;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
use Psy\Exception\FatalErrorException;

class FunctionReturnInWriteContextPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new FunctionReturnInWriteContextPass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Can't use function return value in write context
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return [
            ['f(&g())'],
            ['array(& $object->method())'],
            ['$a->method(& $closure())'],
            ['array(& A::b())'],
            ['f() = 5'],
            ['unset(h())'],
        ];
    }

    public function testIsset()
    {
        try {
            $this->traverser->traverse($this->parse('isset(strtolower("A"))'));
            $this->fail();
        } catch (FatalErrorException $e) {
            if (version_compare(PHP_VERSION, '5.5', '>=')) {
                $this->assertContains(
                    'Cannot use isset() on the result of a function call (you can use "null !== func()" instead)',
                    $e->getMessage()
                );
            } else {
                $this->assertContains("Can't use function return value in write context", $e->getMessage());
            }
        }
    }

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Can't use function return value in write context
     */
    public function testEmpty()
    {
        if (version_compare(PHP_VERSION, '5.5', '>=')) {
            $this->markTestSkipped();
        }

        $this->traverser->traverse($this->parse('empty(strtolower("A"))'));
    }
}
