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

/**
 * The Psy Shell's execution scope.
 */
class ExecutionClosure
{
    const NOOP_INPUT = 'return null;';

    private $closure;

    /**
     * @param Shell &$__psysh__
     */
    public function __construct(Shell &$__psysh__)
    {
        $exec = function () use (&$__psysh__) {
            try {
                // Restore execution scope variables
                extract($__psysh__->getScopeVariables(false));

                // evaluate the current code buffer
                ob_start([$__psysh__, 'writeStdout'], 1);

                set_error_handler([$__psysh__, 'handleError']);
                $_ = eval($__psysh__->onExecute($__psysh__->flushCode() ?: ExecutionClosure::NOOP_INPUT));
                restore_error_handler();

                ob_end_flush();

                $__psysh__->setScopeVariables(get_defined_vars());
            } catch (\Throwable $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                throw $_e;
            } catch (\Exception $_e) {
                restore_error_handler();
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                throw $_e;
            }

            return $_;
        };

        if (self::bindClosure()) {
            $that = $__psysh__->getBoundObject();
            if (is_object($that)) {
                $this->closure = $exec->bindTo($that, get_class($that));
            } else {
                $this->closure = $exec->bindTo(null, null);
            }

            return;
        }

        $this->closure = $exec;
    }

    /**
     * Go go gadget closure.
     *
     * @return mixed
     */
    public function execute()
    {
        $closure = $this->closure;

        return $closure();
    }

    /**
     * Decide whether to bind the execution closure.
     *
     * @return bool
     */
    protected static function bindClosure()
    {
        // skip binding on HHVM <= 3.5.0
        // see https://github.com/facebook/hhvm/issues/1203
        if (defined('HHVM_VERSION')) {
            return version_compare(HHVM_VERSION, '3.5.0', '>=');
        }

        return true;
    }
}
