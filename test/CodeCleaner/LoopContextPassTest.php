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
        return [
            ['continue'],
            ['break'],
            ['if (true) { continue; }'],
            ['if (true) { break; }'],
            ['if (false) { continue; }'],
            ['if (false) { break; }'],
            ['function foo() { break; }'],
            ['function foo() { continue; }'],

            // actually enforce break/continue depth argument
            ['do { break 2; } while (true)'],
            ['do { continue 2; } while (true)'],
            ['for ($a; $b; $c) { break 2; }'],
            ['for ($a; $b; $c) { continue 2; }'],
            ['foreach ($a as $b) { break 2; }'],
            ['foreach ($a as $b) { continue 2; }'],
            ['switch (true) { default: break 2; }'],
            ['switch (true) { default: continue 2; }'],
            ['while (true) { break 2; }'],
            ['while (true) { continue 2; }'],

            // invalid in 5.4+ because they're floats
            // ... in 5.3 because the number is too big
            ['while (true) { break 2.0; }'],
            ['while (true) { continue 2.0; }'],

            // and once with nested loops, just for good measure
            ['while (true) { while (true) { break 3; } }'],
            ['while (true) { while (true) { continue 3; } }'],
        ];
    }

    /**
     * @dataProvider invalidPHP54Statements
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testPHP54ProcessStatementFails($code)
    {
        $stmts = $this->parse($code);
        $this->traverser->traverse($stmts);
    }

    public function invalidPHP54Statements()
    {
        return [
            // In PHP 5.4+, only positive literal integers are allowed
            ['while (true) { break $n; }'],
            ['while (true) { continue $n; }'],
            ['while (true) { break N; }'],
            ['while (true) { continue N; }'],
            ['while (true) { break 0; }'],
            ['while (true) { continue 0; }'],
            ['while (true) { break -1; }'],
            ['while (true) { continue -1; }'],
            ['while (true) { break 1.0; }'],
            ['while (true) { continue 1.0; }'],
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
            ['do { break; } while (true)'],
            ['do { continue; } while (true)'],
            ['for ($a; $b; $c) { break; }'],
            ['for ($a; $b; $c) { continue; }'],
            ['foreach ($a as $b) { break; }'],
            ['foreach ($a as $b) { continue; }'],
            ['switch (true) { default: break; }'],
            ['switch (true) { default: continue; }'],
            ['while (true) { break; }'],
            ['while (true) { continue; }'],

            // `break 1` is redundant, but not invalid
            ['while (true) { break 1; }'],
            ['while (true) { continue 1; }'],

            // and once with nested loops just for good measure
            ['while (true) { while (true) { break 2; } }'],
            ['while (true) { while (true) { continue 2; } }'],
        ];
    }
}
