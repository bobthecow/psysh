<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner;
use Psy\CodeCleaner\MagicConstantsPass;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Scalar_DirConst as DirConstant;
use PHPParser_Node_Scalar_FileConst as FileConstant;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Scalar_String as StringNode;

class MagicConstantsPassTest extends \PHPUnit_Framework_TestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new MagicConstantsPass;
    }

    public function testProcess()
    {
        $stmts = array(new DirConstant);
        $this->pass->process($stmts);
        $this->assertEquals(array(new FunctionCall(new Name('getcwd'))), $stmts);

        $stmts = array(new FileConstant);
        $this->pass->process($stmts);
        $this->assertEquals(array(new StringNode('psysh shell code')), $stmts);
     }
}
