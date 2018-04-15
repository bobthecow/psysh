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
use Psy\CodeCleaner\FunctionContextPass;

class FunctionContextPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new FunctionContextPass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['function foo() { yield; }'],
            ['if (function(){ yield; })'],
        ];
    }

    /**
     * @dataProvider invalidYieldStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testInvalidYield($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidYieldStatements()
    {
        return [
            ['yield'],
            ['if (yield)'],
        ];
    }
}
