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
use PHPParser_Node_Expr_FuncCall as FunctionCall;
use PHPParser_Node_Stmt_Function as FunctionStatement;
use Psy\CodeCleaner\NamespaceAwarePass;
use Psy\Exception\FatalErrorException;

/**
 * Validate that function calls will succeed.
 *
 * This pass throws a FatalErrorException rather than letting PHP run
 * headfirst into a real fatal error and die.
 *
 * @todo Detect and prevent more possible errors (e.g., undefined function when $stmt->name is a Variable)
 */
class ValidFunctionNamePass extends NamespaceAwarePass
{
    /**
     * Validate that function calls will succeed.
     *
     * @throws FatalErrorException if a function is redefined.
     * @throws FatalErrorException if the function name is a string (not an expression) and is not defined.
     *
     * @param mixed $stmt
     */
    protected function processStatement(&$stmt)
    {
        parent::processStatement($stmt);

        if ($stmt instanceof FunctionStatement) {
            $name = $this->getFullyQualifiedName($stmt->name);

            if (function_exists($name) || isset($this->currentScope[$name])) {
                throw new FatalErrorException(sprintf('Cannot redeclare %s()', $name), 0, 1, null, $stmt->getLine());
            }

            $this->currentScope[$name] = true;
        } elseif ($stmt instanceof FunctionCall) {
            // if function name is an expression, give it a pass for now.
            $name = $stmt->name;
            if (!$name instanceof Expression) {
                $shortName = implode('\\', $name->parts);
                $fullName  = $this->getFullyQualifiedName($name);

                if (
                    !isset($this->currentScope[$fullName]) &&
                    !function_exists($shortName) &&
                    !function_exists($fullName)
                ) {
                    $message = sprintf('Call to undefined function %s()', $name);
                    throw new FatalErrorException($message, 0, 1, null, $stmt->getLine());
                }
            }
        }
    }
}
