<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\UseStatementPass;
use PHPParser_Node_Expr_FuncCall as FuncCall;
use PHPParser_Node_Expr_Variable as Variable;

class UseStatementPassTest extends CodeCleanerTestCase
{
    private $pass;

    public function setUp()
    {
        $this->pass = new UseStatementPass;
    }

    /**
     * @dataProvider useStatements
     */
    public function testUseStatement($from, $to)
    {
        $stmts = $this->parse($from);
        $this->pass->process($stmts);
        $this->assertEquals($to, $this->prettyPrint($stmts));
    }

    public function useStatements()
    {
        return array(
            array(
                "use StdClass as NotSoStd;\n\$std = new NotSoStd();",
                "\$std = new StdClass();",
            ),
            array(
                "use Foo\\Bar as fb;\n\$baz = new fb\\Baz();",
                "\$baz = new Foo\\Bar\\Baz();",
            ),
        );
    }
}
