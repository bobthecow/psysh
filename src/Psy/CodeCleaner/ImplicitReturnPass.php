<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node_Expr as Expression;
use PHPParser_Node_Stmt_Return as ReturnStatement;

/**
 * Add an implicit "return" to the last statement, provided it can be returned.
 */
class ImplicitReturnPass extends CodeCleanerPass
{
    /**
     * @param array $nodes
     */
    public function afterTraverse(array $nodes)
    {
        $last = end($nodes);
        if ($last instanceof Expression) {
            $nodes[count($nodes) - 1] = new ReturnStatement($last, array(
                'startLine' => $last->getLine(),
                'endLine'   => $last->getLine(),
            ));
        }

        return $nodes;
    }
}
