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
use Psy\CodeCleaner\LoopContextPass;

class LoopContextPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new LoopContextPass();
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
            array('continue'),
            array('break'),
            array('if (true) { continue; }'),
            array('if (true) { break; }'),
            array('if (false) { continue; }'),
            array('if (false) { break; }'),
            array('function foo() { break; }'),
            array('function foo() { continue; }'),

            // actually enforce break/continue depth argument
            array('do { break 2; } while (true)'),
            array('do { continue 2; } while (true)'),
            array('for ($a; $b; $c) { break 2; }'),
            array('for ($a; $b; $c) { continue 2; }'),
            array('foreach ($a as $b) { break 2; }'),
            array('foreach ($a as $b) { continue 2; }'),
            array('switch (true) { default: break 2; }'),
            array('switch (true) { default: continue 2; }'),
            array('while (true) { break 2; }'),
            array('while (true) { continue 2; }'),

            // invalid in 5.4+ because they're floats
            // ... in 5.3 because the number is too big
            array('while (true) { break 2.0; }'),
            array('while (true) { continue 2.0; }'),

            // and once with nested loops, just for good measure
            array('while (true) { while (true) { break 3; } }'),
            array('while (true) { while (true) { continue 3; } }'),
        );
    }

    /**
     * @dataProvider invalidPHP54Statements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testPHP54ProcessStatementFails($code)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->markTestSkipped();
        }

        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidPHP54Statements()
    {
        return array(
            // In PHP 5.4+, only positive literal integers are allowed
            array('while (true) { break $n; }'),
            array('while (true) { continue $n; }'),
            array('while (true) { break N; }'),
            array('while (true) { continue N; }'),
            array('while (true) { break 0; }'),
            array('while (true) { continue 0; }'),
            array('while (true) { break -1; }'),
            array('while (true) { continue -1; }'),
            array('while (true) { break 1.0; }'),
            array('while (true) { continue 1.0; }'),
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
            array('do { break; } while (true)'),
            array('do { continue; } while (true)'),
            array('for ($a; $b; $c) { break; }'),
            array('for ($a; $b; $c) { continue; }'),
            array('foreach ($a as $b) { break; }'),
            array('foreach ($a as $b) { continue; }'),
            array('switch (true) { default: break; }'),
            array('switch (true) { default: continue; }'),
            array('while (true) { break; }'),
            array('while (true) { continue; }'),

            // `break 1` is redundant, but not invalid
            array('while (true) { break 1; }'),
            array('while (true) { continue 1; }'),

            // and once with nested loops just for good measure
            array('while (true) { while (true) { break 2; } }'),
            array('while (true) { while (true) { continue 2; } }'),
        );
    }
}
