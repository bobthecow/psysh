<?php

namespace Psy\ExecutionLoop;

use Psy\Exception\BreakException;
use Psy\Shell;

class CancellableForkingLoop extends ForkingLoop
{
    public function beforeLoop()
    {
        // allow sigint signal so the handler can intercept
        pcntl_signal(SIGINT, SIG_DFL, true);
        pcntl_signal(SIGINT, function () {
            throw new BreakException('User aborted operation.');
        }, true);
        parent::beforeLoop();
    }

    public function afterLoop()
    {
        // ignore sigint signal
        pcntl_signal(SIGINT, SIG_IGN, true);
        parent::afterLoop();
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

        parent::run($shell);
    }
}
