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
use Psy\CodeCleaner\ExitPass;

class ExitPassTest extends CodeCleanerTestCase
{
    public function setUp()
    {
        $this->pass      = new ExitPass();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->pass);
    }

    /**
     * @expectedException \Psy\Exception\BreakException
     */
    public function testExitStatement()
    {
        $stmts = $this->parse('exit;');
        $this->traverser->traverse($stmts);
    }
}
