<?php

namespace Psy\ExecutionLoop;

use Psy\Exception\BreakException;
use Psy\Shell;

class CancellableForkingLoop extends ForkingLoop
{
    /**
     * @return callable
     */
    protected function getExecutionClosure()
    {
        return function (Shell &$__psysh__) {
            try {
                extract($__psysh__->getScopeVariables());

                // allow sigint signal so the handler can intercept
                pcntl_signal(SIGINT, SIG_DFL, true);
                pcntl_signal(SIGINT, function () {
                    throw new BreakException('User aborted operation.');
                }, true);

                // evaluate the current code buffer
                ob_start(
                    array($__psysh__, 'writeStdout'),
                    version_compare(PHP_VERSION, '5.4', '>=') ? 1 : 2
                );

                set_error_handler(array($__psysh__, 'handleError'));
                $_ = eval($__psysh__->flushCode());
                restore_error_handler();

                ob_end_flush();

                // ignore sigint signal
                pcntl_signal(SIGINT, SIG_IGN, true);

                $__psysh__->writeReturnValue($_);

                $__psysh__->setScopeVariables(get_defined_vars());
            } catch (ThrowUpException $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);
                throw $_e;
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
     * Run the execution loop.
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        declare (ticks = 1);
        // ignore sigint signal
        pcntl_signal(SIGINT, SIG_IGN, true);

        $executionClosure = $this->getExecutionClosure();
        // bind the closure to $this from the shell scope variables...
        if (self::bindLoop()) {
            $executionClosure = $this->setClosureShellScope($shell, $executionClosure);
        }

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
