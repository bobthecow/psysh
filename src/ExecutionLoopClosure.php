<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Exception\BreakException;
use Psy\Exception\InterruptException;
use Psy\Exception\ThrowUpException;

/**
 * The Psy Shell's execution loop scope.
 *
 * @todo Switch ExecutionClosure to a generator and get rid of the duplicate closure implementations?
 */
class ExecutionLoopClosure extends ExecutionClosure
{
    /**
     * @param Shell $__psysh__
     */
    public function __construct(Shell $__psysh__)
    {
        $this->setClosure($__psysh__, function () use ($__psysh__) {
            // Restore execution scope variables
            // @phan-suppress-next-line PhanTypeNonVarPassByRef assigning to a temp variable pollutes scope
            \extract($__psysh__->getScopeVariables(false));

            while (true) {
                $__psysh__->beforeLoop();

                try {
                    $__psysh__->getInput();

                    $__psysh__->beforeExecute();

                    try {
                        // Pull in any new execution scope variables
                        if ($__psysh__->getLastExecSuccess()) {
                            // @phan-suppress-next-line PhanTypeNonVarPassByRef assigning to a temp variable pollutes scope
                            \extract($__psysh__->getScopeVariablesDiff(\get_defined_vars()));
                        }

                        // Evaluate the current code buffer
                        $_ = eval($__psysh__->onExecute($__psysh__->flushCode() ?: ExecutionClosure::NOOP_INPUT));

                        // Flush stdout (write to shell output, plus save to magic variable)
                        $__psysh__->flushExecutionOutput();

                        // Save execution scope variables for next time
                        $__psysh__->setScopeVariables(\get_defined_vars());

                        $__psysh__->writeReturnValue($_);
                    } catch (\Throwable $_e) {
                        $__psysh__->afterExecute($_e);

                        throw $_e;
                    }

                    $__psysh__->afterExecute();
                } catch (BreakException $_e) {
                    // exit() or ctrl-d exits the REPL
                    $__psysh__->writeException($_e);

                    return $_e->getCode();
                } catch (ThrowUpException $_e) {
                    // `throw-up` command throws the exception out of the REPL
                    $__psysh__->writeException($_e);

                    throw $_e;
                } catch (InterruptException $_e) {
                    // ctrl-c stops execution, but continues the REPL
                    $__psysh__->writeException($_e);
                } catch (\Throwable $_e) {
                    // Everything else gets printed to the shell output
                    $__psysh__->writeException($_e);
                } finally {
                    $__psysh__->afterLoop();
                }
            }
        });
    }
}
