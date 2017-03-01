<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use PhpParser\Node\Stmt\GroupUse as GroupUseStmt;
use PhpParser\Node\Stmt\Namespace_ as NamespaceStmt;
use PhpParser\Node\Stmt\Use_ as UseStmt;

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
    private $aliases       = array();
    private $lastAliases   = array();
    private $lastNamespace = null;

    /**
     * Re-load the last set of use statements on re-entering a namespace.
     *
     * This isn't how namespaces normally work, but because PsySH has to spin
     * up a new namespace for every line of code, we do this to make things
     * work like you'd expect.
     *
     * @param Node $node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof NamespaceStmt) {
            // If this is the same namespace as last namespace, let's do ourselves
            // a favor and reload all the aliases...
            if (strtolower($node->name) === strtolower($this->lastNamespace)) {
                $this->aliases = $this->lastAliases;
            }
        }
    }

    /**
     * If this statement is a namespace, forget all the aliases we had.
     *
     * If it's a use statement, remember the alias for later. Otherwise, apply
     * remembered aliases to the code.
     *
     * @param Node $node
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof UseStmt) {
            // Store a reference to every "use" statement, because we'll need
            // them in a bit.
            foreach ($node->uses as $use) {
                $this->aliases[strtolower($use->alias)] = $use->name;
            }

            return false;
        } elseif ($node instanceof GroupUseStmt) {
            // Expand every "use" statement in the group into a full, standalone
            // "use" and store 'em with the others.
            foreach ($node->uses as $use) {
                $this->aliases[strtolower($use->alias)] = Name::concat($node->prefix, $use->name, array(
                    'startLine' => $node->prefix->getAttribute('startLine'),
                    'endLine'   => $use->name->getAttribute('endLine'),
                ));
            }

            return false;
        } elseif ($node instanceof NamespaceStmt) {
            // Start fresh, since we're done with this namespace.
            $this->lastNamespace = $node->name;
            $this->lastAliases   = $this->aliases;
            $this->aliases       = array();
        } else {
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

    /**
     * Find class/namespace aliases.
     *
     * @param Name $name
     *
     * @return FullyQualifiedName|null
     */
    private function findAlias(Name $name)
    {
        $that = strtolower($name);
        foreach ($this->aliases as $alias => $prefix) {
            if ($that === $alias) {
                return new FullyQualifiedName($prefix->toString());
            } elseif (substr($that, 0, strlen($alias) + 1) === $alias . '\\') {
                return new FullyQualifiedName($prefix->toString() . substr($name, strlen($alias)));
            }
        }
    }
}
