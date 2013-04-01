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

/**
 * A CodeCleaner pass interface.
 *
 * CodeCleaner passes are registered with the CodeCleaner. Each pass is run,
 * in turn, on the syntax tree and given a chance to modify it. After all
 * passes have been run, the syntax tree is evaluated.
 */
interface CodeCleanerPassInterface
{
    /**
     * Process the syntax tree.
     *
     * CodeCleaner passes may add, remove, or modify statements.
     *
     * @param mixed &$stmts
     */
    public function process(&$stmts);
}
