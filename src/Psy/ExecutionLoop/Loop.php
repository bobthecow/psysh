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

use Psy\Configuration;
use Psy\Shell;
use Psy\Exception\BreakException;

/**
 * The Psy Shell execution loop.
 */
class Loop
{
    /**
     * The non-forking loop doesn't have much use for Configuration :)
     *
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        // don't need this
    }

    /**
     * Run the execution loop.
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        $loop = function ($__psysh__) {
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

            do {
                $__psysh__->beforeLoop();
                $__psysh__->setScopeVariables(get_defined_vars());

                try {
                    // read a line, see if we should eval
                    $__psysh__->getInput();

                    // evaluate the current code buffer
                    ob_start();

                    set_error_handler(array($__psysh__, 'handleError'));
                    $_ = eval($__psysh__->flushCode());
                    restore_error_handler();

                    $__psysh_out__ = ob_get_contents();
                    ob_end_clean();

                    $__psysh__->writeStdout($__psysh_out__);
                    $__psysh__->writeReturnValue($_);
                } catch (BreakException $_e) {
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
                $__psysh__->afterLoop();
            } while (true);
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
                $loop = $loop->bindTo(null);
            }
        }

        $loop($shell);
    }

    /**
     * A beforeLoop callback.
     *
     * This is executed at the start of each loop iteration. In the default
     * (non-forking) loop implementation, this is a no-op.
     */
    public function beforeLoop()
    {
        // no-op
    }

    /**
     * A afterLoop callback.
     *
     * This is executed at the end of each loop iteration. In the default
     * (non-forking) loop implementation, this is a no-op.
     */
    public function afterLoop()
    {
        // no-op
    }

    /**
     * Decide whether to bind the execution loop.
     *
     * @return boolean
     */
    protected static function bindLoop()
    {
        // skip binding on HHVM <= 3.5.0
        // see https://github.com/facebook/hhvm/issues/1203
        if (defined('HHVM_VERSION')) {
            return version_compare(HHVM_VERSION, '3.5.0', '>=');
        }

        return version_compare(PHP_VERSION, '5.4', '>=');
    }
}
