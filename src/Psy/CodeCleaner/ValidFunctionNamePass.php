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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Function_ as FunctionStmt;
use Psy\Exception\FatalErrorException;

/**
 * Validate that function calls will succeed.
 *
 * This pass throws a FatalErrorException rather than letting PHP run
 * headfirst into a real fatal error and die.
 */
class ValidFunctionNamePass extends NamespaceAwarePass
{
    /**
     * Store newly defined function names on the way in, to allow recursion.
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof FunctionStmt) {
            $name = $this->getFullyQualifiedName($node->name);

            if (function_exists($name) || isset($this->currentScope[strtolower($name)])) {
                throw new FatalErrorException(sprintf('Cannot redeclare %s()', $name), 0, 1, null, $node->getLine());
            }

            $this->currentScope[strtolower($name)] = true;
        }
    }

    /**
     * Validate that function calls will succeed.
     *
     * @throws FatalErrorException if a function is redefined.
     * @throws FatalErrorException if the function name is a string (not an expression) and is not defined.
     *
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof FuncCall) {
            // if function name is an expression or a variable, give it a pass for now.
            $name = $node->name;
            if (!$name instanceof Expr && !$name instanceof Variable) {
                $shortName = implode('\\', $name->parts);
                $fullName  = $this->getFullyQualifiedName($name);
                $inScope = isset($this->currentScope[strtolower($fullName)]);
                if (!$inScope && !function_exists($shortName) && !function_exists($fullName)) {
                    $message = sprintf('Call to undefined function %s()', $name);
                    throw new FatalErrorException($message, 0, 1, null, $node->getLine());
                }
            }
        }
    }
}
