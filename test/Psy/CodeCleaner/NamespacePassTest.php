<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner;
use Psy\CodeCleaner\NamespacePass;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Expr_FuncCall as FuncCall;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;

class NamespacePassTest extends \PHPUnit_Framework_TestCase
{
    private $cleaner;
    private $pass;

    public function setUp()
    {
        $this->cleaner = new CodeCleaner;
        $this->pass = new NamespacePass($this->cleaner);
    }

    public function testProcess()
    {
        $stmts = array(new FuncCall('array_merge'));
        $this->pass->process($stmts);
        $this->assertNull($this->cleaner->getNamespace());

        $stmts = array(new NamespaceStatement(new Name('Psysh')));
        $this->pass->process($stmts);
        $this->assertEquals(array('Psysh'), $this->cleaner->getNamespace());

        $stmts = array(new NamespaceStatement(new Name('MonkeyMonkeyMonkey')));
        $this->pass->process($stmts);
        $this->assertEquals(array('MonkeyMonkeyMonkey'), $this->cleaner->getNamespace());
    }
}
