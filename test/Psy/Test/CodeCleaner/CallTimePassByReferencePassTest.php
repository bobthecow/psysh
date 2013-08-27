<?php

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\CallTimePassByReferencePass;

class CallTimePassByReferencePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass = new CallTimePassByReferencePass();
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessStatementFails($code)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $this->markTestSkipped();
        }

        $stmts = $this->parse($code);
        $this->pass->process($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('f(&$arg)'),
            array('$object->method($first, &$arg)'),
            array('$closure($first, &$arg, $last)'),
            array('A::b(&$arg)'),
        );
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
        $data = array(
            array('array(&$var)'),
            array('$a = &$b'),
            array('f(array(&$b))'),
        );

        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $data = array_merge($data, $this->invalidStatements());
        }

        return $data;
    }
}
