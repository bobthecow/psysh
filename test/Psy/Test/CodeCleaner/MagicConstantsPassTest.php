<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner;
use Psy\CodeCleaner\MagicConstantsPass;
use PHPParser_Node_Name as Name;
use PHPParser_Node_Scalar_DirConst as DirConstant;
use PHPParser_Node_Scalar_FileConst as FileConstant;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Scalar_String as StringNode;

class MagicConstantsPassTest extends CodeCleanerTestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new MagicConstantsPass;
    }

    /**
     * @dataProvider magicConstants
     */
    public function testProcess($from, $to)
    {
        $stmts = $this->parse($from);
        $this->pass->process($stmts);
        $this->assertEquals($to, $this->prettyPrint($stmts));
     }

     public function magicConstants()
     {
        return array(
            array('__DIR__;', 'getcwd();'),
            array('__FILE__;', "'';"),
            array('___FILE___;', "___FILE___;"),
        );
     }
}
