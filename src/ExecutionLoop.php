<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2019 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

/**
 * The Psy Shell execution loop.
 */
class ExecutionLoop
{
    /**
     * Run the execution loop.
     *
     * @throws ThrowUpException if thrown by the `throw-up` command
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        $closure = new ExecutionLoopClosure($shell);
        $closure->execute();
    }
}
