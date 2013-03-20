<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\LeavePsyshAlonePass;

class LeavePsyshAlonePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass = new LeavePsyshAlonePass;
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidProcessArgumentsThrowsException()
    {
        $string = 'Some random string.';
        $this->pass->process($string);
    }

    public function testPassesInlineHtmlThroughJustFine()
    {
        $inline = $this->parse('not php at all!', '');
        $this->pass->process($inline);
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $stmts = $this->parse($code);
        $this->pass->process($stmts);
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
        $this->pass->process($stmts);
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
