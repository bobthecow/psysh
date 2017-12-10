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

use Psy\CodeCleaner\LeavePsyshAlonePass;

class LeavePsyshAlonePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->setPass(new LeavePsyshAlonePass());
    }

    public function testPassesInlineHtmlThroughJustFine()
    {
        $inline = $this->parse('not php at all!', '');
        $this->traverse($inline);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return array(
            array('array_merge()'),
            array('__psysh__()'),
            array('$this'),
            array('$psysh'),
            array('$__psysh'),
            array('$banana'),
        );
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\RuntimeException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverse($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('$__psysh__'),
            array('var_dump($__psysh__)'),
            array('$__psysh__ = "your mom"'),
            array('$__psysh__->fakeFunctionCall()'),
        );
    }
}
