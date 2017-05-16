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
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;
use Psy\Exception\FatalErrorException;

/**
 * The loop context pass handles invalid `break` and `continue` statements.
 */
class LoopContextPass extends CodeCleanerPass
{
    private $isPHP54;
    private $loopDepth;

    public function __construct()
    {
        $this->isPHP54 = version_compare(PHP_VERSION, '5.4.0', '>=');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeTraverse(array $nodes)
    {
        $this->loopDepth = 0;
    }

    /**
     * @throws FatalErrorException if the node is a break or continue in a non-loop or switch context
     * @throws FatalErrorException if the node is trying to break out of more nested structures than exist
     * @throws FatalErrorException if the node is a break or continue and has a non-numeric argument
     * @throws FatalErrorException if the node is a break or continue and has an argument less than 1
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        switch (true) {
            case $node instanceof Do_:
            case $node instanceof For_:
            case $node instanceof Foreach_:
            case $node instanceof Switch_:
            case $node instanceof While_:
                $this->loopDepth++;
                break;

            case $node instanceof Break_:
            case $node instanceof Continue_:
                $operator = $node instanceof Break_ ? 'break' : 'continue';

                if ($this->loopDepth === 0) {
                    throw new FatalErrorException(sprintf("'%s' not in the 'loop' or 'switch' context", $operator));
                }

                if ($node->num instanceof LNumber || $node->num instanceof DNumber) {
                    $num = $node->num->value;
                    if ($this->isPHP54 && ($node->num instanceof DNumber || $num < 1)) {
                        throw new FatalErrorException(sprintf("'%s' operator accepts only positive numbers", $operator));
                    }

                    if ($num > $this->loopDepth) {
                        throw new FatalErrorException(sprintf("Cannot '%s' %d levels", $operator, $num));
                    }
                } elseif ($node->num && $this->isPHP54) {
                    throw new FatalErrorException(sprintf(
                        "'%s' operator with non-constant operand is no longer supported",
                        $operator
                    ));
                }
                break;
        }
    }

    /**
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        switch (true) {
            case $node instanceof Do_:
            case $node instanceof For_:
            case $node instanceof Foreach_:
            case $node instanceof Switch_:
            case $node instanceof While_:
                $this->loopDepth--;
                break;
        }
    }
}
