<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use Psy\Exception\BreakException;
use PhpParser\Node\Expr\Exit_;

class ExitPass extends CodeCleanerPass
{

    /**
     * Throws a PsySH BreakException instead on exit().
     *
     * @throws \Psy\Exception\BreakException if the node is an exit node.
     *
     * @param \PhpParser\Node $node
     */
    public function enterNode(Node $node) {
        if ($node instanceof Exit_) {
            throw new BreakException('Goodbye.');
        }
    }
}
