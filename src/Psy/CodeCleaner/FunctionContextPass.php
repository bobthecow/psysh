<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\Label;
use Psy\Exception\FatalErrorException;

class FunctionContextPass extends CodeCleanerPass
{
    /** @var int */
    private $functionDepth;

    /**
     * @param array $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->functionDepth = 0;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof FunctionLike) {
            $this->functionDepth++;

            return;
        }

        // node is inside function context
        if ($this->functionDepth !== 0) {
            return;
        }

        // It causes fatal error.
        if ($node instanceof Yield_) {
            $msg = 'The "yield" expression can only be used inside a function';
            throw new FatalErrorException($msg, 0, E_ERROR, null, $node->getLine());
        }

        // These statements are meaningless to interactive shell.
        // PsySH does not have facilities for these statements.
        if ($node instanceof Goto_) {
            $msg = 'Can not goto label in PsySH top level.';
            throw new FatalErrorException($msg, 0, E_ERROR, null, $node->getLine());
        } elseif ($node instanceof Label) {
            $msg = 'Can not declare label in PsySH top level.';
            throw new FatalErrorException($msg, 0, E_ERROR, null, $node->getLine());
        }
    }

    /**
     * Converts exit calls to BreakExceptions.
     *
     * @param \PhpParser\Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof FunctionLike) {
            $this->functionDepth--;
        }
    }
}
