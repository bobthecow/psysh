<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ExecutionLoop;

use Psy\Configuration;
use Psy\Shell;
use Psy\Exception\BreakException;

/**
 * The Psy shell execution loop.
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
     * Run the exection loop.
     *
     * @param Shell $shell
     */
    public function run(Shell $shell)
    {
        $loop = function($__psysh__) {
            extract($__psysh__->getScopeVariables());

            do {
                $__psysh__->beforeLoop();

                // a bit of housekeeping
                unset($__psysh_out__, $__psysh_e__);
                $__psysh__->setScopeVariables(get_defined_vars());

                try {
                    // read a line, see if we should eval
                    $__psysh__->getInput();

                    // evaluate the current code buffer
                    ob_start();

                    set_error_handler(array('Psy\Exception\ErrorException', 'throwException'));
                    $_ = eval($__psysh__->flushCode());
                    restore_error_handler();

                    $__psysh_out__ = ob_get_contents();
                    ob_end_clean();

                    $__psysh__->writeStdout($__psysh_out__);
                    $__psysh__->writeReturnValue($_);
                } catch (BreakException $__psysh_e__) {
                    restore_error_handler();
                    $__psysh__->writeException($__psysh_e__);

                    return;
                } catch (\Exception $__psysh_e__) {
                    restore_error_handler();
                    $__psysh__->writeException($__psysh_e__);
                }
            } while (true);
        };

        // bind the closure to $this from the shell scope variables...
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
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
}
