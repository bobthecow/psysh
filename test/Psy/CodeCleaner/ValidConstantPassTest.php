<?php

namespace Psy\Test\CodeCleaner;

use PHPParser_Node_Expr_ConstFetch as ConstantFetch;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Scalar_String as StringNode;
use PHPParser_Node_Stmt_Function as FunctionStatement;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use Psy\CodeCleaner\ValidConstantPass;

class ValidConstantPassTest extends \PHPUnit_Framework_TestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new ValidConstantPass;
    }

    /**
     * @dataProvider getInvalidReferences
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidConstantReferences($stmts)
    {
        $this->pass->process($stmts);
    }

    public function getInvalidReferences()
    {
        return array(
            array(array(
                new ConstantFetch(new Name('Foo\BAR')),
            )),
        );
    }

    /**
     * @dataProvider getValidReferences
     */
    public function testProcessValidConstantReferences($stmts)
    {
        $this->pass->process($stmts);
    }

    public function getValidReferences()
    {
        return array(
            array(array(
                new ConstantFetch(new Name('PHP_EOL')),
            )),
       );
    }
}
