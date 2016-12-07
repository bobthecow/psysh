<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use PhpParser\NodeTraverser;
use Psy\CodeCleaner\PassableByReferencePass;

class PassableByReferencePassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new PassableByReferencePass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @dataProvider invalidStatements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidStatements()
    {
        return array(
            array('array_pop(array())'),
            array('array_pop(array($foo))'),
            array('array_shift(array())'),
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
            array('array_pop(json_decode("[]"))'),
            array('array_pop($foo)'),
            array('array_pop($foo->bar)'),
            array('array_pop($foo::baz)'),
            array('array_pop(Foo::qux)'),
        );
    }
}
