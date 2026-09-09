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

use Psy\ExecutionLoop\ExecutionCleanupListener;

/**
 * Owns the output buffers and error handler for one evaluation.
 *
 * @internal
 */
class ExecutionCleanupState
{
    /** @var ExecutionCleanupListener[] Listeners whose setup completed. */
    public array $listeners = [];

    private int $outputBufferLevel;
    private \Closure $errorHandler;
    /** @var callable|null */
    private $previousErrorHandler;
    private bool $active = true;

    public function __construct(Shell $shell)
    {
        $this->outputBufferLevel = \ob_get_level();
        \ob_start(function (string $out, int $phase) use ($shell): string {
            // A non-removable user buffer can prevent us from closing our own
            // buffer. Stop capturing application output even in that case.
            return $this->active ? $shell->writeStdout($out, $phase) : $out;
        }, 1);

        // A unique handler identifies this frame even when the same shell
        // executes code recursively.
        $this->errorHandler = \Closure::fromCallable([$shell, 'handleError']);
        $this->previousErrorHandler = \set_error_handler($this->errorHandler);
    }

    /**
     * Flush pending output on success, or discard it on failure. Never close
     * buffers that predate this evaluation. Restore the error handler even if
     * an output callback throws. Report the first cleanup failure after
     * attempting to remove all remaining buffers.
     *
     * User code must leave pre-existing buffers and error handlers intact;
     * PHP cannot reconstruct buffers or handler frames removed by user code.
     * Additional callable handlers are unwound here. Code that resets the
     * handler to PHP's default (null) must balance that reset itself, since
     * PHP cannot distinguish a stacked null handler from an empty stack.
     */
    public function closeOutput(bool $flush): void
    {
        if (!$this->active) {
            return;
        }

        $failure = null;

        try {
            while (\ob_get_level() > $this->outputBufferLevel) {
                $level = \ob_get_level();
                $status = \ob_get_status();
                if (!($status['flags'] & \PHP_OUTPUT_HANDLER_REMOVABLE)) {
                    if ($failure === null) {
                        $failure = new \RuntimeException('Unable to close a non-removable output buffer');
                    }
                    break;
                }

                try {
                    if ($flush) {
                        \ob_end_flush();
                    } else {
                        \ob_end_clean();
                    }
                } catch (\Throwable $e) {
                    $failure ??= $e;
                    $flush = false;
                }

                // A callback must not leave us retrying the same buffer forever.
                if (\ob_get_level() >= $level) {
                    if ($failure === null) {
                        $failure = new \RuntimeException('Unable to close an output buffer');
                    }
                    break;
                }
            }
        } finally {
            $this->active = false;
            $this->restoreErrorHandler();
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function restoreErrorHandler(): void
    {
        while (true) {
            // Inspect without changing the current handler or its error mask.
            $handler = \set_error_handler(null);
            \restore_error_handler();

            if ($handler === $this->errorHandler) {
                \restore_error_handler();

                return;
            }

            // Evaluated code may already have restored the caller's handler.
            if ($handler === $this->previousErrorHandler || $handler === null) {
                return;
            }

            \restore_error_handler();
        }
    }
}
