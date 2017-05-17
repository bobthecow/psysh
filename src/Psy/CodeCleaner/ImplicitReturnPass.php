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

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;

/**
 * Add an implicit "return" to the last statement, provided it can be returned.
 */
class ImplicitReturnPass extends CodeCleanerPass
{
    /**
     * @param array $nodes
     *
     * @return array
     */
    public function beforeTraverse(array $nodes)
    {
        return $this->addImplicitReturn($nodes);
    }

    /**
     * @param array $nodes
     *
     * @return array
     */
    private function addImplicitReturn(array $nodes)
    {
        // If nodes is empty, it can't have a return value.
        if (empty($nodes)) {
            return array(new Return_(new New_(new FullyQualifiedName('Psy\CodeCleaner\NoReturnValue'))));
        }

        $last = end($nodes);

        // Special case a few types of statements to add an implicit return
        // value (even though they technically don't have any return value)
        // because showing a return value in these instances is useful and not
        // very surprising.
        if ($last instanceof If_) {
            $last->stmts = $this->addImplicitReturn($last->stmts);

            foreach ($last->elseifs as $elseif) {
                $elseif->stmts = $this->addImplicitReturn($elseif->stmts);
            }

            if ($last->else) {
                $last->else->stmts = $this->addImplicitReturn($last->else->stmts);
            }
        } elseif ($last instanceof Switch_) {
            foreach ($last->cases as $case) {
                // only add an implicit return to cases which end in break
                $caseLast = end($case->stmts);
                if ($caseLast instanceof Break_) {
                    $case->stmts = $this->addImplicitReturn(array_slice($case->stmts, 0, -1));
                    $case->stmts[] = $caseLast;
                }
            }
        } elseif ($last instanceof Expr && !($last instanceof Exit_)) {
            $nodes[count($nodes) - 1] = new Return_($last, array(
                'startLine' => $last->getLine(),
                'endLine'   => $last->getLine(),
            ));
        } elseif ($last instanceof Namespace_) {
            $last->stmts = $this->addImplicitReturn($last->stmts);
        }

        // Return a "no return value" for all non-expression statements, so that
        // PsySH can suppress the `null` that `eval()` returns otherwise.
        //
        // Note that statements special cased above (if/elseif/else, switch)
        // _might_ implicitly return a value before this catch-all return is
        // reached.
        //
        // We're not adding a fallback return after namespace statements,
        // because code outside namespace statements doesn't really work, and
        // there's already an implicit return in the namespace statement anyway.
        if ($last instanceof Stmt && !$last instanceof Return_ && !$last instanceof Namespace_) {
            $nodes[] = new Return_(new New_(new FullyQualifiedName('Psy\CodeCleaner\NoReturnValue')));
        }

        return $nodes;
    }
}
