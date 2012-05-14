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

use PHPParser_Node_Name as Name;
use PHPParser_Node_Name_FullyQualified as FullyQualifiedName;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use Psy\CodeCleaner\CodeCleanerPass;

/**
 * Abstract namespace-aware code cleaner pass.
 */
abstract class NamespaceAwarePass extends CodeCleanerPass
{
    protected $namespace;
    protected $currentScope;

    /**
     * Reset the namespace and the current scope before beginning analysis.
     */
    protected function beginProcess()
    {
        $this->namespace    = array();
        $this->currentScope = array();
    }

    /**
     * @param mixed $stmt PHPParser statement
     *
     * @return void
     */
    protected function processStatement(&$stmt)
    {
        if ($stmt instanceof NamespaceStatement) {
            $this->namespace = isset($stmt->name) ? $stmt->name->parts : array();
        }
    }

    /**
     * Get a fully-qualified name (class, function, interface, etc).
     *
     * @param mixed $name
     *
     * @return string
     */
    protected function getFullyQualifiedName($name)
    {
        if ($name instanceof FullyQualifiedName) {
            return implode('\\', $name->parts);
        } elseif ($name instanceof Name) {
            $name = $name->parts;
        } elseif (!is_array($name)) {
            $name = array($name);
        }

        return implode('\\', array_merge($this->namespace, $name));
    }
}
