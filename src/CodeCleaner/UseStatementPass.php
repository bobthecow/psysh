<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\CodeCleaner;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name; // @phan-suppress-current-line PhanUnreferencedUseNormal - used for type checks
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseItem;
use PhpParser\Node\Stmt\UseUse;
use Psy\Exception\FatalErrorException;

/**
 * Provide implicit use statements for subsequent execution.
 *
 * The use statement pass remembers the last use statement line encountered:
 *
 *     use Foo\Bar as Baz;
 *
 * ... which it then applies implicitly to all future evaluated code, until the
 * current namespace is replaced by another namespace.
 *
 * Extends NamespaceAwarePass to leverage shared alias tracking.
 */
class UseStatementPass extends NamespaceAwarePass
{
    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        // Check for use statement conflicts BEFORE parent adds it to aliases
        // Skip re-injected use statements (marked with 'psyshReinjected' attribute)
        if ($node instanceof Use_ && !$node->getAttribute('psyshReinjected')) {
            $this->validateUseStatement($node);
        }

        return parent::enterNode($node);
    }

    /**
     * Re-inject use statements from previous inputs.
     *
     * Each REPL input is evaluated separately; re-injecting use statements matches PHP behavior for
     * namespaces and use statements in a file.
     *
     * @return Node[]|null Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        parent::beforeTraverse($nodes);

        if (!$this->cleaner) {
            return null;
        }

        // Check for namespace declarations in the input
        foreach ($nodes as $node) {
            if ($node instanceof Namespace_) {
                // Only re-inject use statements if this is a wrapper created by NamespacePass.
                // This matches PHP behavior: explicit namespace declaration clears use statements.
                if ($node->getAttribute('psyshReinjected')) {
                    $aliases = $this->cleaner->getAliasesForNamespace($node->name);
                    if (!empty($aliases)) {
                        $useStatements = $this->createUseStatements($aliases);
                        $node->stmts = \array_merge($useStatements, $node->stmts ?? []);
                    }
                }

                // Don't process other nodes or return modified nodes
                return null;
            }
        }

        // No namespace declaration in input, or re-applied by NamespacePass; re-inject use
        // statements for the empty namespace.
        $aliases = $this->cleaner->getAliasesForNamespace(null);
        if (!empty($aliases)) {
            $useStatements = $this->createUseStatements($aliases);
            $nodes = \array_merge($useStatements, $nodes);
        }

        return $nodes;
    }

    /**
     * If we have aliases but didn't leave a namespace (global namespace case), persist them to
     * CodeCleaner for the next traversal.
     *
     * {@inheritdoc}
     */
    public function afterTraverse(array $nodes)
    {
        if (!$this->cleaner) {
            return null;
        }

        // Persist aliases if they're at the global level (not inside any namespace)
        if (!empty($this->aliases)) {
            $this->cleaner->setAliasesForNamespace(null, $this->aliases);
        }

        return null;
    }

    /**
     * Validate that a use statement doesn't conflict with existing aliases.
     *
     * @throws FatalErrorException if the alias is already in use
     *
     * @param Use_ $stmt The use statement node
     */
    private function validateUseStatement(Use_ $stmt): void
    {
        foreach ($stmt->uses as $useItem) {
            $alias = \strtolower($useItem->getAlias());

            if (isset($this->aliases[$alias])) {
                throw new FatalErrorException(\sprintf('Cannot use %s as %s because the name is already in use', $useItem->name->toString(), $useItem->getAlias()), 0, \E_ERROR, null, $stmt->getStartLine());
            }
        }
    }

    /**
     * Create use statement nodes from stored aliases.
     *
     * @param array $aliases Map of lowercase alias names to Name nodes
     *
     * @return Use_[] Array of use statement nodes
     */
    private function createUseStatements(array $aliases): array
    {
        $useStatements = [];
        foreach ($aliases as $alias => $name) {
            // Create UseItem (PHP-Parser 5.x) or UseUse (PHP-Parser 4.x)
            $useItem = \class_exists(UseItem::class)
                ? new UseItem($name, new Identifier($alias))
                : new UseUse($name, $alias);
            // Mark as re-injected so we don't validate it
            $useStatements[] = new Use_([$useItem], Use_::TYPE_NORMAL, ['psyshReinjected' => true]);
        }

        return $useStatements;
    }
}
