<?php

namespace Psy\ExecutionLoop;

use Psy\Exception\BreakException;
use Psy\Exception\FatalErrorException;
use Psy\Shell;

class CancellableForkingLoop extends ForkingLoop
{
    /**
     * @return callable
     */
    protected function getLoopClosure(\Closure $executionClosure)
    {
        return function (Shell &$__psysh__) use ($executionClosure) {
            // Load user-defined includes
            set_error_handler(array($__psysh__, 'handleError'));
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

                // read a line, see if we should eval
                $__psysh__->getInput();

                // allow sigint signal so the handler can intercept
                pcntl_signal(SIGINT, SIG_DFL, true);
                pcntl_signal(SIGINT, function () {
                    throw new BreakException('User aborted operation.');
                }, true);
                $executionClosure($__psysh__);
                // ignore sigint signal
                pcntl_signal(SIGINT, SIG_IGN, true);

                $__psysh__->afterLoop();
            } while (true);
        };
    }

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
