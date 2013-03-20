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

        // A non-block namespace statement should set the current namespace.
        $stmts = array(new NamespaceStatement(new Name('Alpha')));
        $this->pass->process($stmts);
        $this->assertEquals(array('Alpha'), $this->cleaner->getNamespace());

        // A new non-block namespace statement should override the current namespace.
        $stmts = array(new NamespaceStatement(new Name('Beta')));
        $this->pass->process($stmts);
        $this->assertEquals(array('Beta'), $this->cleaner->getNamespace());

        // Any block namespace statement resets the namespace to null afterward.
        $stmts = array(
            new NamespaceStatement(new Name('Gamma'), array(new FuncCall('array_merge'))),
        );
        $this->pass->process($stmts);
        $this->assertNull($this->cleaner->getNamespace());

        // Reset it before the next one.
        $stmts = array(new NamespaceStatement(new Name('Delta')));
        $this->pass->process($stmts);
        $this->assertEquals(array('Delta'), $this->cleaner->getNamespace());

        // Another block namespace test...
        $stmts = array(
            new FuncCall('array_merge'),
            new NamespaceStatement(new Name('Epsilon')),
        );
        $this->pass->process($stmts);
        $this->assertNull($this->cleaner->getNamespace());
    }
}
