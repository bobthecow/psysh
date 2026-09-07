<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ExecutionLoop;

use Psy\Shell;

/**
 * Execution loop listener with cleanup after executing user code.
 *
 * @todo Add afterExecute to Listener and remove this interface in the next major release.
 */
interface ExecutionCleanupListener extends Listener
{
    /**
     * Called after executing user code, even if execution throws.
     *
     * @param Shell $shell
     */
    public function afterExecute(Shell $shell);
}
