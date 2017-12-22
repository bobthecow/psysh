<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
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
    const NOOP_INPUT = 'return null;';

    /**
     * Execute code in the execution loop context.
     *
     * @todo Should this return Context::getReturnValue?
     *
     * @param Shell  &$shell
     * @param string $code
     */
    public function execute(Shell &$shell, $code)
    {
        $shell->addCode($code, true);
        $exec = $this->getExecutionClosure($shell);

        return $exec($shell);
    }

    /**
     * Run the execution loop.
     *
     * @throws ThrowUpException if thrown by the `throw-up` command
     *
     * @param Shell &$shell
     */
    public function run(Shell &$shell)
    {
        $exec = $this->getExecutionClosure($shell);

        try {
            $this->loadIncludes($shell);

            do {
                $shell->beforeLoop();
                $shell->getInput();
                $exec($shell);
                $shell->afterLoop();
            } while (true);
        } catch (BreakException $e) {
            $shell->writeException($e);
        }
    }

    /**
     * Load user-defined includes.
     *
     * @param Shell &$shell
     */
    protected function loadIncludes(Shell &$shell)
    {
        // Load user-defined includes
        $load = function (Shell &$__psysh__) {
            set_error_handler([$__psysh__, 'handleError']);
            try {
                foreach ($__psysh__->getIncludes() as $__psysh_include__) {
                    include $__psysh_include__;
                }
            } catch (\Exception $_e) {
                $__psysh__->writeException($_e);
            }
            restore_error_handler();
            unset($__psysh_include__);

            $__psysh__->setScopeVariables(get_defined_vars());
        };

        $load($shell);
    }

    /**
     * Get a closure for the execution loop context.
     *
     * @return \Closure
     */
    protected function getExecutionClosure(Shell &$shell)
    {
        $exec = function (Shell &$__psysh__) {
            try {
                // Restore execution scope variables
                extract($__psysh__->getScopeVariables(false));

                // Let PsySH inject some magic variables back into the
                // shell scope... things like $__class, and $__file set by
                // reflection commands
                extract($__psysh__->getSpecialScopeVariables(false));

                // evaluate the current code buffer
                ob_start([$__psysh__, 'writeStdout'], 1);

                set_error_handler([$__psysh__, 'handleError']);
                $_ = eval($__psysh__->onExecute($__psysh__->flushCode() ?: Loop::NOOP_INPUT));
                restore_error_handler();

                ob_end_flush();

                $__psysh__->writeReturnValue($_);

                $__psysh__->setScopeVariables(get_defined_vars());
            } catch (BreakException $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);

                throw $_e;
            } catch (ThrowUpException $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);

                throw $_e;
            } catch (\TypeError $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException(TypeErrorException::fromTypeError($_e));
            } catch (\Error $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException(ErrorException::fromError($_e));
            } catch (\Exception $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);
            }

            return $_;
        };

        if (self::bindLoop()) {
            $that = $shell->getBoundObject();
            if (is_object($that)) {
                return $exec->bindTo($that, get_class($that));
            }

            return $exec->bindTo(null, null);
        }

        return $exec;
    }

    /**
     * Decide whether to bind the execution loop.
     *
     * @return bool
     */
    protected static function bindLoop()
    {
        // skip binding on HHVM <= 3.5.0
        // see https://github.com/facebook/hhvm/issues/1203
        if (defined('HHVM_VERSION')) {
            return version_compare(HHVM_VERSION, '3.5.0', '>=');
        }

        return true;
    }
}
