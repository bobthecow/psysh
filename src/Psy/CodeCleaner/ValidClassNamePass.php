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
use PHPParser_Node_Expr_New as NewExpression;
use PHPParser_Node_Stmt_Class as ClassStatement;
use Psy\CodeCleaner\NamespaceAwarePass;
use Psy\Exception\FatalErrorException;

/**
 * Validate that classes exist.
 *
 * This pass throws a FatalErrorException rather than letting PHP run
 * headfirst into a real fatal error and die.
 *
 * @todo Detect and prevent more possible errors (e.g., undefined class when $stmt->name is a Variable)
 */
class ValidClassNamePass extends NamespaceAwarePass
{
    /**
     * Validate that classes exist.
     *
     * @throws FatalErrorException if a class is redefined.
     * @throws FatalErrorException if the class name is a string (not an expression) and is not defined.
     *
     * @param mixed $stmt
     */
    protected function processStatement(&$stmt)
    {
        parent::processStatement($stmt);

        if ($stmt instanceof ClassStatement) {
            $name = $this->getFullyQualifiedName($stmt->name);

            if (class_exists($name) || isset($this->currentScope[$name])) {
                throw new FatalErrorException(sprintf('Cannot redeclare class %s', $name), 0, 1, null, $stmt->getLine());
            }

            $this->currentScope[$name] = true;
        } elseif ($stmt instanceof NewExpression) {
            // if class name is an expression, give it a pass for now
            if (!$stmt->class instanceof Expression) {
                $name = $this->getFullyQualifiedName($stmt->class);

                if (!isset($this->currentScope[$name]) && !class_exists($name)) {
                    throw new FatalErrorException(sprintf('Class \'%s\' not found', $name), 0, 1, null, $stmt->getLine());
                }
            }
        }
    }
}
