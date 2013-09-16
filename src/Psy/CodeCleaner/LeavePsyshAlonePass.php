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

use PHPParser_Node as Node;
use PHPParser_Node_Expr_Variable as Variable;
use Psy\CodeCleaner\CodeCleanerPass;
use Psy\Exception\RuntimeException;

/**
 * Validate that the user input does not reference the `$__psysh__` variable.
 */
class LeavePsyshAlonePass extends CodeCleanerPass
{
    /**
     * Validate that the user input does not reference the `$__psysh__` variable.
     *
     * @throws RuntimeException if the user is messing with $__psysh__.
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Variable && $node->name === "__psysh__") {
            throw new RuntimeException('Don\'t mess with $__psysh__. Bad things will happen.');
        }
    }
}
