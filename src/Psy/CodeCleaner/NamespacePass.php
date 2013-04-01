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

use PHPParser_Node_Name as Name;
use PHPParser_Node_Stmt_Namespace as NamespaceStatement;
use Psy\CodeCleaner;

/**
 * Provide implicit namespaces for subsequent execution.
 *
 * The namespace pass remembers the last standalone namespace line encountered:
 *
 *     namespace Foo\Bar;
 *
 * ... which it then applies implicitly to all future evaluated code, until the
 * namespace is replaced by another namespace. To reset to the top level
 * namespace, enter `namespace {}`. This is a bit ugly, but it does the trick :)
 */
class NamespacePass implements CodeCleanerPassInterface
{
    private $namespace = null;
    private $cleaner;

    /**
     * @param CodeCleaner $cleaner
     */
    public function __construct(CodeCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    /**
     * If this is a standalone namespace line, remember it for later.
     *
     * Otherwise, apply remembered namespaces to the code until a new namespace
     * is encountered.
     *
     * @param mixed &$stmts
     */
    public function process(&$stmts)
    {
        $first = reset($stmts);
        if (count($stmts) === 1 && $first instanceof NamespaceStatement && empty($first->stmts)) {
            $this->setNamespace($first->name);
        } else {
            foreach ($stmts as $key => $stmt) {
                if ($stmt instanceof NamespaceStatement) {
                    $this->setNamespace(null);
                } elseif ($this->namespace !== null) {
                    $stmts[$key] = new NamespaceStatement($this->namespace, array($stmt));
                }
            }
        }
    }

    /**
     * Remember the namespace and (re)set the namespace on the CodeCleaner as
     * well.
     *
     * @param null|Name $namespace
     */
    private function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        $this->cleaner->setNamespace($namespace === null ? null : $namespace->parts);
    }
}
