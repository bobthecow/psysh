<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Return_ as ReturnStmt;

/**
 * Add an implicit "return" to the last statement, provided it can be returned.
 */
class ImplicitReturnPass extends CodeCleanerPass
{
    /**
     * @param array $nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $last = end($nodes);

        if ($last instanceof Expr) {
            $nodes[count($nodes) - 1] = new ReturnStmt($last, array(
                'startLine' => $last->getLine(),
                'endLine'   => $last->getLine(),
            ));
        }

        return $nodes;
    }
}
