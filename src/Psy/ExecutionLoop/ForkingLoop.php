<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ExecutionLoop;

use Psy\Exception\FatalErrorException;
use Psy\Shell;

/**
 * A forking version of the Psy Shell execution loop.
 *
 * This version is preferred, as it won't die prematurely if user input includes
 * a fatal error, such as redeclaring a class or function.
 */
class ForkingLoop extends Loop
{
    /**
     * @param Shell $shell
     * @param $loop
     */
    protected function replay(Shell $shell, $loop)
    {
        try {
            $loop($shell);
        } catch (FatalErrorException $e) {
            $shell->resetCodeBuffer();
            $shell->writeException($e);
            $this->replay($shell, $loop);
        }
    }

    /**
     * Run the execution loop.
     *
     * Forks into a master and a loop process. The loop process will handle the
     * evaluation of all instructions, then return its state via a socket upon
     * completion.
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        $executionClosure = $this->getExecutionClosure();
        // bind the closure to $this from the shell scope variables...
        if (self::bindLoop()) {
            $executionClosure = $this->setClosureShellScope($shell, $executionClosure);
        }

        $loop = $this->getLoopClosure($executionClosure);

        while (true) {
            $shell->setExitLoop(false);

            if (function_exists('setproctitle')) {
                setproctitle('psysh (loop)');
            }

            $this->replay($shell, $loop);

            if ($shell->getExitLoop()) {
                break;
            }
        }
    }
}
