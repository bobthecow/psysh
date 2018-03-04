<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ListPass;

class ListPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new ListPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\ParseErrorException
     */
    public function testProcessInvalidStatement($code, $expected_message)
    {
        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException('Psy\Exception\ParseErrorException', $expected_message);
        } else {
            $this->expectExceptionMessage($expected_message);
        }

        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        // Not typo.  It is ambiguous whether "Syntax" or "syntax".
        $error_short_list_assign = "yntax error, unexpected '='";
        $error_empty_list = 'Cannot use empty list';
        $error_assoc_list_assign = 'Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, expecting \',\' or \')\'';
        $error_non_variable_assign = 'Assignments can only happen to writable values';
        $error_php_parser_syntax = 'PHP Parse error: Syntax error, unexpected';

        $invalid_expr = [
            ['list() = array()', $error_empty_list],
            ['list("a") = array(1)', $error_php_parser_syntax],
        ];

        $invalid_before_71 = [
            ['list("a" => _) = array("a" => 1)', $error_php_parser_syntax],
            ['[] = []', $error_short_list_assign],
            ['[$a] = [1]', $error_short_list_assign],
            ['list("a" => $a) = array("a" => 1)', $error_assoc_list_assign],
        ];

        $invalid_after_71 = [
            ['list("a" => _) = array("a" => 1)', $error_php_parser_syntax],
            ['[] = []', $error_empty_list],
        ];

        if (version_compare(PHP_VERSION, '7.1', '<')) {
            $invalid_expr = array_merge($invalid_expr, $invalid_before_71);
        } else {
            $invalid_expr = array_merge($invalid_expr, $invalid_after_71);
        }

        return $invalid_expr;
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        $valid_expr = [
            ['list($a) = array(1)'],
            ['list($x, $y) = array(1, 2)'],
        ];

        if (version_compare(PHP_VERSION, '7.1', '>=')) {
            return array_merge($valid_expr, [
                ['[$a] = array(1)'],
                ['list($b) = [2]'],
                ['[$x, $y] = array(1, 2)'],
                ['[$a] = [1]'],
                ['[$x, $y] = [1, 2]'],
                ['["_" => $v] = ["_" => 1]'],
            ]);
        }

        return $valid_expr;
    }
}
