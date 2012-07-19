<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
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
class ImplicitReturnPass implements CodeCleanerPassInterface
{
    /**
     * @param array &$stmts
     */
    public function process(&$stmts)
    {
        $last = end($stmts);
        if ($last instanceof Expression) {
            $stmts[count($stmts) - 1] = new ReturnStatement($last, array(
                'startLine' => $last->getLine(),
                'endLine'   => $last->getLine(),
            ));
        }
    }
}
