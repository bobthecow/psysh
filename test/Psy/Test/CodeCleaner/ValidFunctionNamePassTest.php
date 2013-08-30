<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidFunctionNamePass;

class ValidFunctionNamePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass = new ValidFunctionNamePass;
    }

    /**
     * @dataProvider getInvalidFunctions
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidFunctionCallsAndDeclarations($code)
    {
        $stmts = $this->parse($code);
        $this->pass->process($stmts);
    }

    public function getInvalidFunctions()
    {
        return array(
            // function declarations
            array('function array_merge() {}'),
            array('function Array_Merge() {}'),
            array("
                function psy_test_codecleaner_validfunctionnamepass_alpha() {}
                function psy_test_codecleaner_validfunctionnamepass_alpha() {}
            "),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function beta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function beta() {}
                }
            "),

            // function calls
            array('psy_test_codecleaner_validfunctionnamepass_gamma()'),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    delta();
                }
            "),
        );
    }

    /**
     * @dataProvider getValidFunctions
     */
    public function testProcessValidFunctionCallsAndDeclarations($code)
    {
        $stmts = $this->parse($code);
        $this->pass->process($stmts);
    }

    public function getValidFunctions()
    {
        return array(
            array('function psy_test_codecleaner_validfunctionnamepass_epsilon() {}'),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function zeta() {}
                }
            "),
            array("
                namespace {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
            "),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
                namespace {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
            "),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function array_merge() {}
                }
            "),

            // function calls
            array('array_merge();'),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function theta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    theta();
                }
            "),
            array("
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function theta() {}
                }
                namespace {
                    Psy\\Test\\CodeCleaner\\ValidFunctionNamePass\\theta();
                }
            "),
        );
    }
}
