<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PHPParser_Node_Expr as Expression;
use PHPParser_Node_Expr_New as NewExpression;
use PHPParser_Node_Stmt as Statement;
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
     * Validate class, interface and trait statements, and `new` expressions.
     *
     * @throws FatalErrorException if a class, interface or trait is referenced which does not exist.
     * @throws FatalErrorException if a class extends something that is not a class.
     * @throws FatalErrorException if a class implements something that is not an interface.
     * @throws FatalErrorException if an interface extends something that is not an interface.
     * @throws FatalErrorException if a class, interface or trait redefines an existing class, interface or trait name.
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
            $this->validateNewExpression($stmt);
        }
    }

    /**
     * Validate a class definition statment.
     *
     * @param ClassStatement $stmt
     */
    protected function validateClassStatement(ClassStatement $stmt)
    {
        $this->ensureCanDefine($stmt);
        if (isset($stmt->extends)) {
            $this->ensureClassExists($this->getFullyQualifiedName($stmt->extends), $stmt);
        }
        $this->ensureInterfacesExist($stmt->implements, $stmt);
    }

    /**
     * Validate an interface definition statment.
     *
     * @param InterfaceStatement $stmt
     */
    protected function validateInterfaceStatement(InterfaceStatement $stmt)
    {
        $this->ensureCanDefine($stmt);
        $this->ensureInterfacesExist($stmt->extends, $stmt);
    }

    /**
     * Validate a trait definition statment.
     *
     * @param TraitStatement $stmt
     */
    protected function validateTraitStatement(TraitStatement $stmt)
    {
        $this->ensureCanDefine($stmt);
    }

    /**
     * Validate a `new` expression.
     *
     * @param NewExpression $stmt
     */
    protected function validateNewExpression(NewExpression $stmt)
    {
        // if class name is an expression, give it a pass for now
        if (!$stmt->class instanceof Expression) {
            $this->ensureClassExists($this->getFullyQualifiedName($stmt->class), $stmt);
        }
    }

    /**
     * Ensure that no class, interface or trait name collides with a new definition.
     *
     * @param  Statement $stmt
     */
    protected function ensureCanDefine(Statement $stmt)
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

    /**
     * Ensure that a referenced class exists.
     *
     * @param  string    $name
     * @param  Statement $stmt
     */
    protected function ensureClassExists($name, $stmt)
    {
        if (!$this->classExists($name)) {
            throw $this->createError(sprintf('Class \'%s\' not found', $name), $stmt);
        }
    }

    /**
     * Ensure that a referenced interface exists.
     *
     * @param  string    $name
     * @param  Statement $stmt
     */
    protected function ensureInterfacesExist($interfaces, $stmt)
    {
        foreach ($interfaces as $interface) {
            $name = $this->getFullyQualifiedName($interface);
            if (!$this->interfaceExists($name)) {
                throw $this->createError(sprintf('Interface \'%s\' not found', $name), $stmt);
            }
        }
    }

    /**
     * Get a symbol type key for storing in the scope name cache.
     *
     * @param  Statement $stmt [description]
     *
     * @return string
     */
    protected function getScopeType(Statement $stmt)
    {
        if ($stmt instanceof ClassStatement) {
            return self::CLASS_TYPE;
        } elseif ($stmt instanceof InterfaceStatement) {
            return self::INTERFACE_TYPE;
        } elseif ($stmt instanceof TraitStatement) {
            return self::TRAIT_TYPE;
        }
    }

    /**
     * Check whether a class exists, or has been defined in the current code snippet.
     *
     * @param  string $name
     *
     * @return boolean
     */
    protected function classExists($name)
    {
        return class_exists($name) || $this->findInScope($name) == self::CLASS_TYPE;
    }

    /**
     * Check whether an interface exists, or has been defined in the current code snippet.
     *
     * @param  string $name
     *
     * @return boolean
     */
    protected function interfaceExists($name)
    {
        return interface_exists($name) || $this->findInScope($name) == self::INTERFACE_TYPE;
    }

    /**
     * Check whether a trait exists, or has been defined in the current code snippet.
     *
     * @param  string $name
     *
     * @return boolean
     */
    protected function traitExists($name)
    {
        return $this->checkTraits && (trait_exists($name) || $this->findInScope($name) == self::TRAIT_TYPE);
    }

    /**
     * Find a symbol in the current code snippet scope.
     *
     * @param  string $name
     *
     * @return string
     */
    protected function findInScope($name)
    {
        if (isset($this->currentScope[$name])) {
            return $this->currentScope[$name];
        }
    }

    /**
     * Error creation factory
     *
     * @param  string    $msg
     * @param  Statement $stmt
     *
     * @return FatalErrorException
     */
    protected function createError($msg, $stmt)
    {
        return new FatalErrorException($msg, 0, 1, null, $stmt->getLine());
    }
}
