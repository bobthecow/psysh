<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ValidFunctionNamePass;

class ValidFunctionNamePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ValidFunctionNamePass());
    }

    /**
     * @dataProvider getInvalidFunctions
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidFunctionCallsAndDeclarations($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }

    public function getInvalidFunctions()
    {
        return array(
            // function declarations
            array('function array_merge() {}'),
            array('function Array_Merge() {}'),
            array('
                function psy_test_codecleaner_validfunctionnamepass_alpha() {}
                function psy_test_codecleaner_validfunctionnamepass_alpha() {}
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function beta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function beta() {}
                }
            '),

            // function calls
            array('psy_test_codecleaner_validfunctionnamepass_gamma()'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    delta();
                }
            '),

            // recursion
            array('function a() { a(); } function a() {}'),
        );
    }

    /**
     * @dataProvider getValidFunctions
     */
    public function testProcessValidFunctionCallsAndDeclarations($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    public function getValidFunctions()
    {
        return array(
            array('function psy_test_codecleaner_validfunctionnamepass_epsilon() {}'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function zeta() {}
                }
            '),
            array('
                namespace {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
                namespace {
                    function psy_test_codecleaner_validfunctionnamepass_eta() {}
                }
            '),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function array_merge() {}
                }
            '),

            // function calls
            array('array_merge();'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function theta() {}
                }
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    theta();
                }
            '),
            // closures
            array('$test = function(){};$test()'),
            array('
                namespace Psy\\Test\\CodeCleaner\\ValidFunctionNamePass {
                    function theta() {}
                }
                namespace {
                    Psy\\Test\\CodeCleaner\\ValidFunctionNamePass\\theta();
                }
            '),

            // recursion
            array('function a() { a(); }'),

            // conditionally defined functions
            array('
                function a() {}
                if (false) {
                    function a() {}
                }
            '),
            array('
                function a() {}
                if (true) {
                    function a() {}
                } else if (false) {
                    function a() {}
                } else {
                    function a() {}
                }
            '),
            // ewww
            array('
                function a() {}
                if (true):
                    function a() {}
                elseif (false):
                    function a() {}
                else:
                    function a() {}
                endif;
            '),
            array('
                function a() {}
                while (false) { function a() {} }
            '),
            array('
                function a() {}
                do { function a() {} } while (false);
            '),
            array('
                function a() {}
                switch (1) {
                    case 0:
                        function a() {}
                        break;
                    case 1:
                        function a() {}
                        break;
                    case 2:
                        function a() {}
                        break;
                }
            '),
        );
    }
}
