<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2022 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use Psy\Exception\FatalErrorException;

/**
 * Validate that the constructor method is not static, and does not have a
 * return type.
 *
 * Checks both explicit __construct methods as well as old-style constructor
 * methods with the same name as the class (for non-namespaced classes).
 *
 * As of PHP 5.3.3, methods with the same name as the last element of a
 * namespaced class name will no longer be treated as constructor. This change
 * doesn't affect non-namespaced classes.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ValidConstructorPass extends CodeCleanerPass
{
    private $namespace;

    /**
     * @return Node[]|null Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->namespace = [];
    }

    /**
     * Validate that the constructor is not static and does not have a return type.
     *
     * @throws FatalErrorException the constructor function is static
     * @throws FatalErrorException the constructor function has a return type
     *
     * @param Node $node
     *
     * @return int|Node|null Replacement node (or special return value)
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->namespace = isset($node->name) ? $node->name->parts : [];
        } elseif ($node instanceof Class_) {
            $constructor = null;
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof ClassMethod) {
                    // If we find a new-style constructor, no need to look for the old-style
                    if ('__construct' === \strtolower($stmt->name)) {
                        $this->validateConstructor($stmt, $node);

                        return;
                    }

                    // We found a possible old-style constructor (unless there is also a __construct method)
                    if (empty($this->namespace) && \strtolower($node->name) === \strtolower($stmt->name)) {
                        $constructor = $stmt;
                    }
                }
            }

            if ($constructor) {
                $this->validateConstructor($constructor, $node);
            }
        }
    }

    /**
     * @throws FatalErrorException the constructor function is static
     * @throws FatalErrorException the constructor function has a return type
     *
     * @param Node $constructor
     * @param Node $classNode
     */
    private function validateConstructor(Node $constructor, Node $classNode)
    {
        if ($constructor->isStatic()) {
            // For PHP Parser 4.x
            $className = $classNode->name instanceof Identifier ? $classNode->name->toString() : $classNode->name;

            $msg = \sprintf(
                'Constructor %s::%s() cannot be static',
                \implode('\\', \array_merge($this->namespace, (array) $className)),
                $constructor->name
            );
            throw new FatalErrorException($msg, 0, \E_ERROR, null, $classNode->getLine());
        }

        if (\method_exists($constructor, 'getReturnType') && $constructor->getReturnType()) {
            // For PHP Parser 4.x
            $className = $classNode->name instanceof Identifier ? $classNode->name->toString() : $classNode->name;

            $msg = \sprintf(
                'Constructor %s::%s() cannot declare a return type',
                \implode('\\', \array_merge($this->namespace, (array) $className)),
                $constructor->name
            );
            throw new FatalErrorException($msg, 0, \E_ERROR, null, $classNode->getLine());
        }
    }
}
