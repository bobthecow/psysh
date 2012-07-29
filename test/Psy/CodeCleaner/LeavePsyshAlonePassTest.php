<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\LeavePsyshAlonePass;
use PHPParser_Node_Expr_FuncCall as FuncCall;
use PHPParser_Node_Expr_Variable as Variable;

class LeavePsyshAlonePassTest extends \PHPUnit_Framework_TestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new LeavePsyshAlonePass;
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidProcessArgumentsThrowsException()
    {
        $string = 'Some random string.';
        $this->pass->process($string);
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($stmt)
    {
        $stmts = array($stmt);
        $this->pass->process($stmts);
    }

    public function validStatements()
    {
        return array(
            array(new FuncCall('array_merge')),
            array(new FuncCall('__psysh__')),
            array(new Variable('this')),
            array(new Variable('psysh')),
            array(new Variable('__psysh')),
            array(new Variable('banana')),
        );
    }

    /**
     * @expectedException \Psy\Exception\RuntimeException
     */
    public function testProcessStatementFails()
    {
        $stmts = array(new Variable('__psysh__'));
        $this->pass->process($stmts);
    }
}
