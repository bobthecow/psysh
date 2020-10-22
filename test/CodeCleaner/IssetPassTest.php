<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\IssetPass;

/**
 * Code cleaner to check for invalid isset() arguments.
 */
class IssetPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new IssetPass());
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     * @expectedExceptionMessage Cannot use isset() on the result of an expression (you can use "null !== expression" instead)
     */
    public function testProcessStatementFails($code)
    {
        $this->parseAndTraverse($code);
    }

    public function invalidStatements()
    {
        return [
          ['isset(1)'],
          ['isset(3.14)'],
          ['isset("a")'],
          ['isset([])'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testValidStatements($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            // Multiple scalar variables in a group
            ['isset($a, $b, $c)'],

            // Arrays and string offsets
            ['isset($var)'],
            ['isset($var[1])'],
            ['isset($var, $var[1])'],
            ['isset($var[1][2])'],
            ['isset($var["a"])'],
            ['isset($var[false])'],
            ['isset($var[0.6])'],
            ['isset($var[true])'],
            ['isset($var[null])'],
            ['isset($var[STDIN])'],
            ['isset($var[$key = "b"])'],
            ['isset($var[M_PI])'],
            ['isset($var[[]])'],
            ['isset($var[new stdClass()])'],

            // Objects
            ['isset($a->b)'],

            // isset() can be used on dereferences of temporary expressions
            // TODO: as of which version?
            ['isset([0, 1][0])'],
            ['isset(([0, 1] + [])[0])'],
            ['isset([[0, 1]][0][0])'],
            ['isset(([[0, 1]] + [])[0][0])'],
            ['isset(((object) ["a" => "b"])->a)'],
            ['isset(["a" => "b"]->a)'],
            ['isset("str"->a)'],
            ['isset((["a" => "b"] + [])->a)'],
            ['isset((["a" => "b"] + [])->a->b)'],

            // $this
            ['isset($this)'],
            ['isset($this->foo)'],
            ['isset($this[0])'],

            // Nullsafe operator
            ['isset($foo?->bar)'],
            ['isset($foo?->bar->baz)'],

            // Variable variables, and other errata
            ['isset($$wat)'],
            ['isset($$$wat)'],
            ['isset(${"wat"})'],
        ];
    }
}
