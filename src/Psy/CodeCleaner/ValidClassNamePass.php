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
use PHPParser_Node_Stmt_Interface as InterfaceStatement;
use PHPParser_Node_Stmt_Trait as TraitStatement;
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
    const CLASS_TYPE     = 'class';
    const INTERFACE_TYPE = 'interface';
    const TRAIT_TYPE     = 'trait';

    protected $checkTraits;

    public function __construct()
    {
        $this->checkTraits = function_exists('trait_exists');
    }

    /**
     * Validate that classes exist.
     *
     * @throws FatalErrorException if a class is redefined.
     * @throws FatalErrorException if the class name is a string (not an expression) and is not defined.
     *
     * @param mixed &$stmt
     */
    protected function processStatement(&$stmt)
    {
        parent::processStatement($stmt);

        if ($stmt instanceof ClassStatement) {
            $this->validateClassStatement($stmt);
        } elseif ($stmt instanceof InterfaceStatement) {
            $this->validateInterfaceStatement($stmt);
        } elseif ($stmt instanceof TraitStatement) {
            $this->validateTraitStatement($stmt);
        } elseif ($stmt instanceof NewExpression) {
            $this->validateNewStatement($stmt);
        }
    }

    protected function validateClassStatement($stmt)
    {
        $this->ensureCanCreate($stmt);
        if (isset($stmt->extends)) {
            $this->ensureClassExists($this->getFullyQualifiedName($stmt->extends), $stmt);
        }
        $this->ensureInterfacesExist($stmt->implements, $stmt);
    }

    protected function validateInterfaceStatement($stmt)
    {
        $this->ensureCanCreate($stmt);
        $this->ensureInterfacesExist($stmt->extends, $stmt);
    }

    protected function validateTraitStatement($stmt)
    {
        $this->ensureCanCreate($stmt);
    }

    protected function validateNewStatement($stmt)
    {
        // if class name is an expression, give it a pass for now
        if (!$stmt->class instanceof Expression) {
            $this->ensureClassExists($this->getFullyQualifiedName($stmt->class), $stmt);
        }
    }

    protected function ensureCanCreate($stmt)
    {
        $name = $this->getFullyQualifiedName($stmt->name);

        // check for name collisions
        $errorType = null;
        if ($this->classExists($name)) {
            $errorType = self::CLASS_TYPE;
        } elseif ($this->interfaceExists($name)) {
            $errorType = self::INTERFACE_TYPE;
        } elseif ($this->traitExists($name)) {
            $errorType = self::TRAIT_TYPE;
        }

        if ($errorType !== null) {
            throw $this->createError(sprintf('%s named %s already exists', ucfirst($errorType), $name), $stmt);
        }

        // Store creation for the rest of this code snippet so we can find local
        // issue too
        $this->currentScope[$name] = $this->getScopeType($stmt);
    }


    protected function ensureClassExists($name, $stmt)
    {
        if (!$this->classExists($name)) {
            throw $this->createError(sprintf('Class \'%s\' not found', $name), $stmt);
        }
    }

    protected function ensureInterfacesExist($interfaces, $stmt)
    {
        foreach ($interfaces as $interface) {
            $name = $this->getFullyQualifiedName($interface);
            if (!$this->interfaceExists($name)) {
                throw $this->createError(sprintf('Interface \'%s\' not found', $name), $stmt);
            }
        }
    }

    protected function getScopeType($stmt)
    {
        if ($stmt instanceof ClassStatement) {
            return self::CLASS_TYPE;
        } elseif ($stmt instanceof InterfaceStatement) {
            return self::INTERFACE_TYPE;
        } elseif ($stmt instanceof TraitStatement) {
            return self::TRAIT_TYPE;
        }
    }

    protected function classExists($name)
    {
        return class_exists($name) || $this->findInScope($name) == self::CLASS_TYPE;
    }

    protected function interfaceExists($name)
    {
        return interface_exists($name) || $this->findInScope($name) == self::INTERFACE_TYPE;
    }

    protected function traitExists($name)
    {
        return $this->checkTraits && (trait_exists($name) || $this->findInScope($name) == self::TRAIT_TYPE);
    }

    protected function findInScope($name)
    {
        if (isset($this->currentScope[$name])) {
            return $this->currentScope[$name];
        }
    }

    protected function createError($msg, $stmt)
    {
        return new FatalErrorException($msg, 0, 1, null, $stmt->getLine());
    }
}
