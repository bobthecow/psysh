<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
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
    }

    public function validStatements()
    {
        return array(
            array('function foo() { yield; }'),
            array('if (function(){ yield; })'),
        );
    }

    /**
     * @dataProvider invalidYieldStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testInvalidYield($code)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $this->markTestSkipped();
        }

        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidYieldStatements()
    {
        return array(
            array('yield'),
            array('if (yield)'),
        );
    }
}
