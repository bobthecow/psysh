<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Exception\BreakException;
use Psy\Exception\ErrorException;
use Psy\Exception\ThrowUpException;
use Psy\Exception\TypeErrorException;

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
        $this->loadIncludes($shell);

        $closure = new ExecutionClosure($shell);

        do {
            $shell->beforeLoop();

            try {
                $shell->getInput();
                $_ = $closure->execute();
                $shell->writeReturnValue($_);
            } catch (BreakException $_e) {
                $shell->writeException($_e);

                return;
            } catch (ThrowUpException $_e) {
                $shell->writeException($_e);

                throw $_e;
            } catch (\TypeError $_e) {
                $shell->writeException(TypeErrorException::fromTypeError($_e));
            } catch (\Error $_e) {
                $shell->writeException(ErrorException::fromError($_e));
            } catch (\Exception $_e) {
                $shell->writeException($_e);
            }

            $shell->afterLoop();
        } while (true);
    }

    /**
     * Load user-defined includes.
     *
     * @param Shell $shell
     */
    protected function loadIncludes(Shell $shell)
    {
        // Load user-defined includes
        $load = function (Shell $__psysh__) {
            set_error_handler([$__psysh__, 'handleError']);
            foreach ($__psysh__->getIncludes() as $__psysh_include__) {
                try {
                    include $__psysh_include__;
                } catch (\Error $_e) {
                    $__psysh__->writeException(ErrorException::fromError($_e));
                } catch (\Exception $_e) {
                    $__psysh__->writeException($_e);
                }
            }
            restore_error_handler();
            unset($__psysh_include__);

            // Override any new local variables with pre-defined scope variables
            extract($__psysh__->getScopeVariables(false));

            // ... then add the whole mess of variables back.
            $__psysh__->setScopeVariables(get_defined_vars());
        };

        $load($shell);
    }
}
