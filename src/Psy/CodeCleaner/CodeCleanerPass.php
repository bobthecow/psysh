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

use Psy\CodeCleaner\CodeCleanerPassInterface;

/**
 * An abstract CodeCleaner pass.
 *
 * Recursively walks the syntax tree and calls `processStatement` on each element.
 * Includes a `beginProcess` and `endProcess` callback for the sake of subclasses.
 */
abstract class CodeCleanerPass implements CodeCleanerPassInterface
{

    /**
     * Process the syntax tree.
     *
     * CodeCleaner passes may add, remove, or modify statements.
     *
     * @param mixed &$stmts
     */
    public function process(&$stmts)
    {
        if (!is_array($stmts) && !$stmts instanceof \Traversable) {
            throw new \InvalidArgumentException('Unable to validate non-traversable sets.');
        }

        $this->beginProcess();
        $this->processStatements($stmts);
        $this->endProcess();
    }

    /**
     * Pre-processing callback.
     */
    protected function beginProcess()
    {
        // nothing to see here
    }

    /**
     * Recursively process each statement in the syntax tree.
     *
     * @param mixed &$stmts
     */
    protected function processStatements(&$stmts)
    {
        foreach ($stmts as $key => $stmt) {
            if ($this->processStatement($stmt) === false) {
                // Per the processStatement call, unset this bad boy
                $this->removeStatement($stmts, $key);
            } elseif (is_array($stmt) || (is_object($stmt) && $stmt instanceof \Traversable)) {
                // Otherwise continue processing
                $this->processStatements($stmt);
            }
        }
    }

    /**
     * Post-processing callback.
     */
    protected function endProcess()
    {
        // nothing to see here
    }

    /**
     * Subclasses implement a statement processor method, which is recursively
     * called on each element in the syntax tree.
     *
     * @param mixed &$stmt
     */
    abstract protected function processStatement(&$stmt);

    private function removeStatement(&$stmts, $key)
    {
        if (is_object($stmts)) {
            unset($stmts->$key);
        } elseif (is_array($stmts)) {
            unset($stmts[$key]);
        }
    }

}
