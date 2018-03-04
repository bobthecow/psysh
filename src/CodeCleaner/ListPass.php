<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;
use Psy\Exception\ParseErrorException;

/**
 * Validate that the list assignment.
 */
class ListPass extends CodeCleanerPass
{
    /**
     * Validate use of list assignment.
     *
     * @throws ParseErrorException if the user used empty with anything but a variable
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Assign) {
            return;
        }

        if (!$node->var instanceof Array_ && !$node->var instanceof List_) {
            return;
        }

        $before_php71 = version_compare(PHP_VERSION, '7.1', '<');

        if ($node->var instanceof Array_ && $before_php71) {
            $msg = "syntax error, unexpected '='";
            throw new ParseErrorException($msg, $node->expr->getLine());
        }

        if ($node->var->items === [] || $node->var->items === [null]) {
            throw new ParseErrorException('Cannot use empty list', $node->var->getLine());
        }

        foreach ($node->var->items as $item) {
            if ($item === null) {
                throw new ParseErrorException('Cannot use empty list', $item->getLine());
            }

            if ($before_php71 && $item->key !== null) {
                $msg = 'syntax error, unexpected \'\'x\'\' (T_CONSTANT_ENCAPSED_STRING), expecting \',\' or \')\'';
                throw new ParseErrorException($msg, $item->key->getLine());
            }

            if (!$item->value instanceof Variable) {
                $msg = 'Assignments can only happen to writable values';
                throw new ParseErrorException($msg, $item->value->getLine());
            }
        }
    }
}
