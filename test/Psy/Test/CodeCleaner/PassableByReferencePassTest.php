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

        // @todo a better thing to assert here?
        $this->assertTrue(true);
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

    /**
     * @dataProvider validArrayMultisort
     */
    public function testArrayMultisort($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);

        // @todo a better thing to assert here?
        $this->assertTrue(true);
    }

    public function validArrayMultisort()
    {
        return array(
            array('array_multisort($a)'),
            array('array_multisort($a, $b)'),
            array('array_multisort($a, SORT_NATURAL, $b)'),
            array('array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b)'),
            array('array_multisort($a, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE, $b)'),
            array('array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, SORT_ASC, $b)'),
            array('array_multisort($a, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'),
            array('array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'),
            array('array_multisort($a, 1, $b)'),
            array('array_multisort($a, 1 + 2, $b)'),
            array('array_multisort($a, getMultisortFlags(), $b)'),
        );
    }

    /**
     * @dataProvider invalidArrayMultisort
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testInvalidArrayMultisort($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidArrayMultisort()
    {
        return array(
            array('array_multisort(1)'),
            array('array_multisort(array(1, 2, 3))'),
            array('array_multisort($a, SORT_NATURAL, SORT_ASC, SORT_NATURAL, $b)'),
        );
    }
}
