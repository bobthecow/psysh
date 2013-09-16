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
use PHPParser_Node_Name as Name;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use PHPParser_Node_Stmt_Use as UseStatement;

/**
 * Provide implicit use statements for subsequent execution.
 *
 * The use statement pass remembers the last use statement line encountered:
 *
 *     use Foo\Bar as Baz;
 *
 * ... which it then applies implicitly to all future evaluated code, until the
 * current namespace is replaced by another namespace.
 */
class UseStatementPass extends NamespaceAwarePass
{
    private $aliases = array();

    /**
     * If this statement is a namespace, forget all the aliases we had. If it's
     * a use statement, remember the alias for later. Otherwise, apply
     * remembered aliases to the code.
     *
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof UseStatement) {
            // Store a reference to every "use" statement, because we'll need
            // them in a bit.
            foreach ($node->uses as $use) {
                $this->aliases[strtolower($use->alias)] = $use->name;
            }

            return false;
        } elseif ($node instanceof NamespaceStatement) {
            // start fresh, since this is a new namespace.
            $this->aliases = array();
        } elseif ($node instanceof \Traversable) {
            foreach ($node as $name => $subNode) {
                if ($subNode instanceof Name) {
                    // Implicitly thunk all aliases.
                    if ($replacement = $this->findAlias($subNode)) {
                        $node->$name = $replacement;
                    }
                }
            }

            return $node;
        }
    }

    private function findAlias(Name $name)
    {
        $that = strtolower($name);
        foreach ($this->aliases as $alias => $prefix) {
            if ($that === $alias) {
                return $prefix;
            } elseif (substr($that, 0, strlen($alias) + 1) === $alias.'\\') {
                return new Name($prefix->toString() . substr($name, strlen($alias)));
            }
        }
    }
}
