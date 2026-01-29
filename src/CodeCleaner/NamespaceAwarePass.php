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
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified as FullyQualifiedName;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use Psy\CodeCleaner;

/**
 * Abstract namespace-aware code cleaner pass.
 *
 * Tracks both namespace and use statement aliases for proper name resolution.
 */
abstract class NamespaceAwarePass extends CodeCleanerPass
{
    protected array $namespace = [];
    protected array $currentScope = [];
    protected array $aliases = [];
    protected ?CodeCleaner $cleaner = null;

    /**
     * Set the CodeCleaner instance for state management.
     */
    public function setCleaner(CodeCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    /**
     * @todo should this be final? Extending classes should be sure to either
     * use afterTraverse or call parent::beforeTraverse() when overloading.
     *
     * Reset the namespace and the current scope before beginning analysis
     *
     * @return Node[]|null Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->namespace = [];
        $this->currentScope = [];

        return null;
    }

    /**
     * @todo should this be final? Extending classes should be sure to either use
     * leaveNode or call parent::enterNode() when overloading
     *
     * @param Node $node
     *
     * @return int|Node|null Replacement node (or special return value)
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->namespace = isset($node->name) ? $this->getParts($node->name) : [];

            // Only restore use statement aliases for PsySH re-injected namespaces.
            // Explicit namespace declarations start with a clean slate.
            if ($this->cleaner && $node->getAttribute('psyshReinjected')) {
                $this->aliases = $this->cleaner->getAliasesForNamespace($node->name);
            } else {
                $this->aliases = [];
            }
        }

        // Track use statements for alias resolution
        if ($node instanceof Use_) {
            foreach ($node->uses as $useItem) {
                $this->aliases[\strtolower($useItem->getAlias())] = $useItem->name;
            }
        }

        // Track group use statements
        if ($node instanceof GroupUse) {
            foreach ($node->uses as $useItem) {
                $this->aliases[\strtolower($useItem->getAlias())] = Name::concat($node->prefix, $useItem->name);
            }
        }

        return null;
    }

    /**
     * Save alias state when leaving a namespace.
     *
     * Braced namespaces (like `namespace { ... }`) are self-contained and don't persist their use
     * statements between executions.
     *
     * Only save aliases for open namespaces (like `namespace Foo;`), or implicit namespace wrappers
     * re-injected by PsySH (psyshReinjected).
     *
     * {@inheritdoc}
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            // Open namespaces (like `namespace Foo;`) have kind == KIND_SEMICOLON.
            if ($node->getAttribute('kind') === Namespace_::KIND_SEMICOLON || $node->getAttribute('psyshReinjected')) {
                if ($this->cleaner) {
                    $this->cleaner->setAliasesForNamespace($node->name, $this->aliases);
                }
            }

            $this->aliases = [];
        }

        return null;
    }

    /**
     * Get a fully-qualified name (class, function, interface, etc).
     *
     * Resolves use statement aliases before applying namespace.
     *
     * @param mixed $name
     */
    protected function getFullyQualifiedName($name): string
    {
        if ($name instanceof FullyQualifiedName) {
            return \implode('\\', $this->getParts($name));
        }

        // Check if this name matches a use statement alias
        if ($name instanceof Name) {
            $nameParts = $this->getParts($name);
            $firstPart = \strtolower($nameParts[0]);

            if (isset($this->aliases[$firstPart])) {
                // Replace first part with the aliased namespace
                $aliasedParts = $this->getParts($this->aliases[$firstPart]);
                \array_shift($nameParts);  // Remove first part

                return \implode('\\', \array_merge($aliasedParts, $nameParts));
            }
        }

        if ($name instanceof Name) {
            $name = $this->getParts($name);
        } elseif (!\is_array($name)) {
            $name = [$name];
        }

        return \implode('\\', \array_merge($this->namespace, $name));
    }

    /**
     * Backwards compatibility shim for PHP-Parser 4.x.
     *
     * At some point we might want to make $namespace a plain string, to match how Name works?
     */
    protected function getParts(Name $name): array
    {
        return \method_exists($name, 'getParts') ? $name->getParts() : $name->parts;
    }
}
