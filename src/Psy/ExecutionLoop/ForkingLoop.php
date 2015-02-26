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

use Psy\Exception\BreakException;
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
     * @param $executionClosure
     */
    protected function replay(Shell $shell, $executionClosure)
    {
        try {
            // get input
            $shell->getInput();

            $shell->beforeLoop();
            $executionClosure($shell);
            $shell->afterLoop();
        } catch (BreakException $e) {
            $shell->writeException($e);
            $shell->setExitLoop($e);
        } catch (\Exception $e) {
            $shell->resetCodeBuffer();
            $shell->writeException($e);
            $this->replay($shell, $executionClosure);
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

        $this->setIncludes($shell);

        while (true) {
            $shell->setExitLoop(false);

            if (function_exists('setproctitle')) {
                setproctitle('psysh (loop)');
            }

            $this->replay($shell, $executionClosure);

            if ($shell->getExitLoop()) {
                break;
            }
        }
    }
}
