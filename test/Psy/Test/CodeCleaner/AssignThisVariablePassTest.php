<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PHPParser_NodeTraverser as NodeTraverser;
use Psy\CodeCleaner\AssignThisVariablePass;

class AssignThisVariablePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new AssignThisVariablePass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('$this = 3'),
            array('strtolower($this = "this")'),
        );
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
            array('$this'),
            array('$a = $this'),
            array('$a = "this"; $$a = 3'),
            array('$$this = "b"'),
        );
    }
}
