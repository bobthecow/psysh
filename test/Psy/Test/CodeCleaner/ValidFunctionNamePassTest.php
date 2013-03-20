<?php

namespace Psy\Test\CodeCleaner;

use PHPParser_Node_Name as Name;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Stmt_Function as FunctionStatement;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use Psy\CodeCleaner\ValidFunctionNamePass;

class ValidFunctionNamePassTest extends \PHPUnit_Framework_TestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new ValidFunctionNamePass;
    }

    /**
     * @dataProvider getInvalidFunctions
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidFunctionCallsAndDeclarations($stmts)
    {
        $this->pass->process($stmts);
    }

    public function getInvalidFunctions()
    {
        return array(
            // function declarations
            array(array(
                new FunctionStatement('array_merge'),
            )),
            array(array(
                new FunctionStatement('Array_Merge'),
            )),
            array(array(
                new FunctionStatement('psy_test_codecleaner_validfunctionnamepass_alpha'),
                new FunctionStatement('psy_test_codecleaner_validfunctionnamepass_alpha'),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('beta'),
                )),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('beta'),
                )),
            )),

            // function calls
            array(array(
                new FunctionCall(new Name('psy_test_codecleaner_validfunctionnamepass_gamma')),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionCall(new Name('delta')),
                )),
            )),
        );
    }

    /**
     * @dataProvider getValidFunctions
     */
    public function testProcessValidFunctionCallsAndDeclarations($stmts)
    {
        $this->pass->process($stmts);
    }

    public function getValidFunctions()
    {
        return array(
            array(array(
                new FunctionStatement('psy_test_codecleaner_validfunctionnamepass_epsilon'),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('zeta'),
                )),
            )),
            array(array(
                new FunctionStatement('psy_test_codecleaner_validfunctionnamepass_eta'),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('psy_test_codecleaner_validfunctionnamepass_eta'),
                )),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('array_merge'),
                )),
            )),

            // function calls
            array(array(
                new FunctionCall(new Name('array_merge')),
            )),
            array(array(
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionStatement('theta'),
                )),
                new NamespaceStatement(new Name('Psy\Test\CodeCleaner\ValidFunctionNamePass'), array(
                    new FunctionCall(new Name('theta')),
                )),
            )),
       );
    }
}
