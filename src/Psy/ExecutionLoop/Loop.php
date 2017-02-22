<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ExecutionLoop;

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\Exception\ErrorException;
use Psy\Exception\ThrowUpException;
use Psy\Exception\TypeErrorException;
use Psy\Shell;

/**
 * The Psy Shell execution loop.
 */
class Loop
{
    const NOOP_INPUT = 'return null;';

    /**
     * Loop constructor.
     *
     * The non-forking loop doesn't have much use for Configuration, so we'll
     * just ignore it.
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
     * @throws ThrowUpException if thrown by the `throw-up` command
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

            extract($__psysh__->getScopeVariables(false));

            do {
                $__psysh__->beforeLoop();
                $__psysh__->setScopeVariables(get_defined_vars());

                try {
                    // read a line, see if we should eval
                    $__psysh__->getInput();

                    // evaluate the current code buffer
                    ob_start(
                        array($__psysh__, 'writeStdout'),
                        version_compare(PHP_VERSION, '5.4', '>=') ? 1 : 2
                    );

                    // Let PsySH inject some magic variables back into the
                    // shell scope... things like $__class, and $__file set by
                    // reflection commands
                    extract($__psysh__->getSpecialScopeVariables(false));

                    // And unset any magic variables which are no longer needed
                    foreach ($__psysh__->getUnusedCommandScopeVariableNames() as $__psysh_var_name__) {
                        unset($$__psysh_var_name__, $__psysh_var_name__);
                    }

                    set_error_handler(array($__psysh__, 'handleError'));
                    $_ = eval($__psysh__->flushCode() ?: Loop::NOOP_INPUT);
                    restore_error_handler();

                    ob_end_flush();

                    $__psysh__->writeReturnValue($_);
                } catch (BreakException $_e) {
                    restore_error_handler();
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    $__psysh__->writeException($_e);

                    return;
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

                $__psysh__->afterLoop();
            } while (true);
        };

        // bind the closure to $this from the shell scope variables...
        if (self::bindLoop()) {
            $that = $shell->getBoundObject();
            if (is_object($that)) {
                $loop = $loop->bindTo($that, get_class($that));
            } else {
                $loop = $loop->bindTo(null, null);
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
     * @return bool
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
