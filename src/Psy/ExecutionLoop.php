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

    /** @var \Psy\CodeCleaner  */
    protected $cleaner;

    /**
     * Constructor.
     *
     * @param CodeCleaner $cleaner
     */
    public function __construct(CodeCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    /**
     * @param  Shell $shell
     * @param  $code
     * @return mixed
     */
    public function execute(Shell &$shell, $code)
    {
        $code = $this->cleaner->clean(array($code));

        $executionClosure = $this->getExecutionClosure();
        if (self::bindLoop()) {
            $executionClosure = $this->setClosureShellScope($shell, $executionClosure);
        }

        return $executionClosure($shell, $code);
    }

    /**
     * Run the execution loop.
     *
     * @throws ThrowUpException if thrown by the `throw-up` command
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

        try {
            $loop($shell);
        } catch (BreakException $_e) {
            $shell->writeException($_e);
        }
    }

    /**
     * @return callable
     */
    protected function getLoopClosure(\Closure $executionClosure)
    {
        return function (Shell &$__psysh__) use ($executionClosure) {
            // Load user-defined includes
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

            do {
                $__psysh__->beforeLoop();
                $__psysh__->setScopeVariables(get_defined_vars());

                // read a line, see if we should eval
                $__psysh__->getInput();

                $executionClosure($__psysh__);

                $__psysh__->afterLoop();
            } while (true);
        };
    }

    /**
     * @return callable
     */
    protected function getExecutionClosure()
    {
        return function (Shell &$__psysh__, $code = null) {
            try {
                // evaluate the current code buffer
                ob_start(array($__psysh__, 'writeStdout'), 1);

                // Restore execution scope variables
                extract($__psysh__->getScopeVariables(false));

                // Let PsySH inject some magic variables back into the
                // shell scope... things like $__class, and $__file set by
                // reflection commands
                extract($__psysh__->getSpecialScopeVariables(false));

                set_error_handler(array($__psysh__, 'handleError'));
                $_ = eval($__psysh__->onExecute($code ?: ($__psysh__->flushCode() ?: Loop::NOOP_INPUT)));
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
        };
    }

    /**
     * @param $loop
     * @return mixed
     */
    protected function setClosureShellScope(Shell $shell, $loop)
    {
        $that = $shell->getBoundObject();
        if (is_object($that)) {
            return $loop->bindTo($that, get_class($that));
        }

        return $loop->bindTo(null, null);
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
