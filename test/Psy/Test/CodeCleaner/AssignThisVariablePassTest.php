<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\AssignThisVariablePass;

class AssignThisVariablePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass = new AssignThisVariablePass();
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->pass->process($stmts);
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
        $this->pass->process($stmts);
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
