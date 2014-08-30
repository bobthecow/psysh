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
use Psy\CodeCleaner\CalledClassPass;

class CalledClassPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new CalledClassPass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\ErrorException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('get_class()'),
            array('get_class(null)'),
            array('get_called_class()'),
            array('get_called_class(null)'),
            array('function foo() { return get_class(); }'),
            array('function foo() { return get_class(null); }'),
            array('function foo() { return get_called_class(); }'),
            array('function foo() { return get_called_class(null); }'),
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
        return array(
            array('get_class($foo)'),
            array('get_class(bar())'),
            array('get_called_class($foo)'),
            array('get_called_class(bar())'),
            array('function foo($bar) { return get_class($bar); }'),
            array('function foo($bar) { return get_called_class($bar); }'),
            array('class Foo { function bar() { return get_class(); } }'),
            array('class Foo { function bar() { return get_class(null); } }'),
            array('class Foo { function bar() { return get_called_class(); } }'),
            array('class Foo { function bar() { return get_called_class(null); } }'),
            array('$foo = function () {}; $foo()'),
        );
    }

    /**
     * @dataProvider validTraitStatements
     */
    public function testProcessTraitStatementPasses($code)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            $this->markTestSkipped();
        }

        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function validTraitStatements()
    {
        return array(
            array('trait Foo { function bar() { return get_class(); } }'),
            array('trait Foo { function bar() { return get_class(null); } }'),
            array('trait Foo { function bar() { return get_called_class(); } }'),
            array('trait Foo { function bar() { return get_called_class(null); } }'),
        );
    }
}
