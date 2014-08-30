<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PHPParser_NodeTraverser as NodeTraverser;
use Psy\CodeCleaner\CallTimePassByReferencePass;

class CallTimePassByReferencePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new CallTimePassByReferencePass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
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
        $this->traverser->traverse($stmts);
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
        $this->traverser->traverse($stmts);
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
