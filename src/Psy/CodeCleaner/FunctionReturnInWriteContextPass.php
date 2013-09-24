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
use PHPParser_Node_Expr_Array as ArrayNode;
use PHPParser_Node_Expr_Empty as EmptyNode;
use PHPParser_Node_Expr_Isset as IssetNode;
use PHPParser_Node_Expr_Assign as AssignNode;
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Expr_MethodCall as MethodCall;
use PHPParser_Node_Expr_StaticCall as StaticCall;
use Psy\Exception\FatalErrorException;

/**
 * Validate that the functions are used correctly.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class FunctionReturnInWriteContextPass extends CodeCleanerPass
{
    const EXCEPTION_MESSAGE = "Can't use function return value in write context";
    private $isPHP_5_5;

    public function __construct()
    {
        $this->isPHP_5_5 = version_compare(PHP_VERSION, '5.5', '>=');
    }

    /**
     * Validate that the functions are used correctly.
     *
     * @throws FatalRuntimeException if a function is passed as an argument reference
     * @throws FatalRuntimeException if a function is used as an argument in the isset
     * @throws FatalRuntimeException if a function is used as an argument in the empty, only for PHP < 5.5
     * @throws FatalRuntimeException if a value is assigned to a function
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof ArrayNode || $this->isCallNode($node)) {
            $items = $node instanceof ArrayNode ? $node->items : $node->args;
            foreach ($items as $item) {
                if ($item->byRef && $this->isCallNode($item->value)) {
                    throw new FatalErrorException(self::EXCEPTION_MESSAGE);
                }
            }
        } elseif ($node instanceof IssetNode) {
            foreach ($node->vars as $var) {
                if (!$this->isCallNode($var)) {
                    continue;
                }

                if ($this->isPHP_5_5) {
                    throw new FatalErrorException('Cannot use isset() on the result of a function call (you can use "null !== func()" instead)');
                } else {
                    throw new FatalErrorException(self::EXCEPTION_MESSAGE);
                }
            }
        } elseif ($node instanceof EmptyNode && !$this->isPHP_5_5 && $this->isCallNode($node->expr)) {
            throw new FatalErrorException(self::EXCEPTION_MESSAGE);
        } elseif ($node instanceof AssignNode && $this->isCallNode($node->var)) {
            throw new FatalErrorException(self::EXCEPTION_MESSAGE);
        }
    }

    private function isCallNode(Node $node)
    {
        return $node instanceof FunctionCall || $node instanceof MethodCall || $node instanceof StaticCall;
    }
}
