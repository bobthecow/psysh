<?php

namespace Psy\ExecutionLoop;

use Psy\Exception\BreakException;
use Psy\Shell;

/**
 * Class CancellableForkingLoop
 * @package Psy\ExecutionLoop
 */
class CancellableForkingLoop extends Loop
{
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

        $loop = function (Shell $__psysh__) {
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

            extract($__psysh__->getScopeVariables());

            $__psysh__->setScopeVariables(get_defined_vars());

            try {
                // read a line, see if we should eval
                $__psysh__->getInput();

                // evaluate the current code buffer
                ob_start();

                // allow sigint signal so the handler can intercept
                pcntl_signal(SIGINT, SIG_DFL, true);
                set_error_handler(array($__psysh__, 'handleError'));
                $_ = eval($__psysh__->flushCode());
                restore_error_handler();
                // ignore sigint signal
                pcntl_signal(SIGINT, SIG_IGN, true);

                $__psysh_out__ = ob_get_contents();
                ob_end_clean();

                $__psysh__->writeStdout($__psysh_out__);
                $__psysh__->writeReturnValue($_);
            } catch (BreakException $_e) {
                $__psysh__->setExitLoop(true);
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);

                return;
            } catch (\Exception $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $__psysh__->writeException($_e);
            }

            // a bit of housekeeping
            unset($__psysh_out__);
        };

        // bind the closure to $this from the shell scope variables...
        if (self::bindLoop()) {
            $that = null;
            try {
                $that = $shell->getScopeVariable('this');
            } catch (\InvalidArgumentException $e) {
                // well, it was worth a shot
            }

            if (is_object($that)) {
                $loop = $loop->bindTo($that, get_class($that));
            } else {
                $loop = $loop->bindTo(null, null);
            }
        }

        list($up, $down) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if (!$up) {
            throw new \RuntimeException('Unable to create socket pair.');
        }

        while (true) {
            $shell->setExitLoop(false);
            $pid = pcntl_fork();
            if ($pid < 0) {
                throw new \RuntimeException('Unable to start execution loop.');
            } elseif ($pid > 0) {
                // This is the main thread. We'll just wait for a while.

                $cancelled = false;
                // interception of the sigint signal
                pcntl_signal(SIGINT, function () use ($pid, &$cancelled) {
                    $cancelled = true;
                    posix_kill($pid, SIGKILL);
                    pcntl_signal_dispatch();
                }, true);
                pcntl_waitpid($pid, $status);

                if (!$cancelled && !pcntl_wexitstatus($status)) {
                    $shell->setExitLoop(true);
                }

                // We won't be needing this one.
                @fclose($up);

                // Wait for a return value from the loop process.
                $read = array($down);
                $write = null;
                $except = null;
                @stream_select($read, $write, $except, null);

                $content = @stream_get_contents($down);
                @fclose($down);

                if ($content) {
                    $shell->setScopeVariables(@unserialize($content));
                }
            } else {
                // This is the child process. It's going to do all the work.
                if (function_exists('setproctitle')) {
                    setproctitle('psysh (loop)');
                }

                // We won't be needing this one.
                @fclose($down);

                // Let's do some processing.
                $loop($shell);

                // Send the scope variables back up to the main thread
                @fwrite($up, $this->serializeReturn($shell->getScopeVariables()));
                @fclose($up);
            }

            if ($shell->getExitLoop()) {
                exit;
            }
        }
    }

    /**
     * Serialize all serializable return values.
     *
     * A naïve serialization will run into issues if there is a Closure or
     * SimpleXMLElement (among other things) in scope when exiting the execution
     * loop. We'll just ignore these unserializable classes, and serialize what
     * we can.
     *
     * @param array $return
     *
     * @return string
     */
    protected function serializeReturn(array $return)
    {
        $serializable = array();
        foreach ($return as $key => $value) {
            try {
                serialize($value);
                $serializable[$key] = $value;
            } catch (\Exception $e) {
                // we'll just ignore this one...
            }
        }

        return serialize($serializable);
    }
}
