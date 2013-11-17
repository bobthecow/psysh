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
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use PHPParser_Node_Stmt_Class as ClassStatement;
use PHPParser_Node_Stmt_ClassMethod as ClassMethod;
use Psy\Exception\FatalErrorException;

/**
 * Validate that the old-style constructor function is not static.
 *
 * As of PHP 5.3.3, methods with the same name as the last element of a namespaced class name
 * will no longer be treated as constructor. This change doesn't affect non-namespaced classes.
 *
 * Validation of the __construct method ensures the PHP Parser.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class StaticConstructorPass extends CodeCleanerPass
{
    private $isPHP533;
    private $namespace;

    public function __construct()
    {
        $this->isPHP533 = version_compare(PHP_VERSION, '5.3.3', '>=');
    }

    public function beforeTraverse(array $nodes)
    {
        $this->namespace = array();
    }

    /**
     * Validate that the old-style constructor function is not static.
     *
     * @throws FatalErrorException if the old-style constructor function is static.
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof NamespaceStatement) {
            $this->namespace = isset($node->name) ? $node->name->parts : array();
        } elseif ($node instanceof ClassStatement) {

            // Bail early if this is PHP 5.3.3 and we have a namespaced class
            if (!empty($this->namespace) && $this->isPHP533) {
                return;
            }

            $constructor = null;
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof ClassMethod) {
                    // Bail early if we find a new-style constructor
                    if ('__construct' === strtolower($stmt->name)) {
                        return;
                    }

                    // We found a possible old-style constructor
                    // (unless there is also a __construct method)
                    if (strtolower($node->name) === strtolower($stmt->name)) {
                        $constructor = $stmt;
                    }
                }
            }

            if ($constructor && $constructor->isStatic()) {
                throw new FatalErrorException(sprintf(
                    'Constructor %s::%s() cannot be static',
                    implode('\\', array_merge($this->namespace, (array) $node->name)),
                    $constructor->name
                ));
            }
        }
    }
}
