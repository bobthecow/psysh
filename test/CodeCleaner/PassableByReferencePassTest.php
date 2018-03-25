<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
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
        return [
            ['array_pop(array())'],
            ['array_pop(array($foo))'],
            ['array_shift(array())'],
        ];
    }

    /**
     * @dataProvider validStatements
     */
    public function testProcessStatementPasses($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validStatements()
    {
        return [
            ['array_pop(json_decode("[]"))'],
            ['array_pop($foo)'],
            ['array_pop($foo->bar)'],
            ['array_pop($foo::baz)'],
            ['array_pop(Foo::qux)'],
        ];
    }

    /**
     * @dataProvider validArrayMultisort
     */
    public function testArrayMultisort($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
        $this->assertTrue(true);
    }

    public function validArrayMultisort()
    {
        return [
            ['array_multisort($a)'],
            ['array_multisort($a, $b)'],
            ['array_multisort($a, SORT_NATURAL, $b)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b)'],
            ['array_multisort($a, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE, $b)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, SORT_ASC, $b)'],
            ['array_multisort($a, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'],
            ['array_multisort($a, SORT_NATURAL | SORT_FLAG_CASE, $b, SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE)'],
            ['array_multisort($a, 1, $b)'],
            ['array_multisort($a, 1 + 2, $b)'],
            ['array_multisort($a, getMultisortFlags(), $b)'],
        ];
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
        return [
            ['array_multisort(1)'],
            ['array_multisort(array(1, 2, 3))'],
            ['array_multisort($a, SORT_NATURAL, SORT_ASC, SORT_NATURAL, $b)'],
        ];
    }
}
