<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\InstanceOfPass;

class InstanceOfPassTest extends CodeCleanerTestCase
{
    protected function setUp()
    {
        $this->setPass(new InstanceOfPass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessInvalidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('null instanceof StdClass'),
            array('true instanceof StdClass'),
            array('9 instanceof StdClass'),
            array('1.0 instanceof StdClass'),
            array('"foo" instanceof StdClass'),
            array('__DIR__ instanceof StdClass'),
            array('PHP_SAPI instanceof StdClass'),
            array('1+1 instanceof StdClass'),
            array('true && false instanceof StdClass'),
            array('"a"."b" instanceof StdClass'),
            array('!5 instanceof StdClass'),
        );
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessValidStatement($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function validStatements()
    {
        $data = array(
            array('$a instanceof StdClass'),
            array('strtolower("foo") instanceof StdClass'),
            array('array(1) instanceof StdClass'),
            array('(string) "foo" instanceof StdClass'),
            array('(1+1) instanceof StdClass'),
            array('"foo ${foo} $bar" instanceof StdClass'),
            array('DateTime::ISO8601 instanceof StdClass'),

        );

        return $data;
    }
}
