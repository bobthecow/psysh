<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\LabelContextPass;

class LabelContextPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new LabelContextPass());
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['function foo() { foo: "echo"; goto foo; }'],
            ['function foo() { "echo"; goto foo; }'],
            ['begin: foreach (range(1, 5) as $i) { goto end; } end: goto begin;'],
            ['bar: if (true) goto bar;'],

            // False negative
            // PHP Fatal error: 'goto' into loop or switch statement is disallowed
            'false negative1' => ['while (true) { label: "error"; } goto label;'],
            // PHP Fatal error:  'goto' to undefined label 'none'
            'false negative2' => ['$f = function () { goto none; };'],
        ];
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testInvalid($code)
    {
        $this->parseAndTraverse($code);
    }

    public function invalidStatements()
    {
        return [
            ['goto bar;'],
            ['if (true) goto bar;'],
            ['buz: if (true) goto bar;'],
        ];
    }

    /**
     * @dataProvider unreachableLabelStatements
     */
    public function testUnreachedLabel($code)
    {
        $this->parseAndTraverse($code);
        $this->assertTrue(true);
    }

    public function unreachableLabelStatements()
    {
        return [
            ['buz:'],
            ['foo: buz: goto foo;'],
            ['foo: buz: goto buz;'],
        ];
    }
}
