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

use Psy\Shell;

/**
 * Execution Loop Listener interface.
 */
interface Listener
{
    /**
     * Determines whether this listener should be active.
     *
     * @return bool
     */
    public static function isSupported();

    /**
     * Called once before the REPL session starts.
     *
     * @param Shell $shell
     */
    public function beforeRun(Shell $shell);

    /**
     * Called at the start of each loop.
     *
     * @param Shell $shell
     */
    public function beforeLoop(Shell $shell);

    /**
     * Called on user input.
     *
     * @param Shell  $shell
     * @param string $input
     *
     * @return string
     */
    public function onInput(Shell $shell, $input);

    /**
     * Called before executing user code.
     *
     * @param Shell  $shell
     * @param string $code
     *
     * @return string
     */
    public function onExecute(Shell $shell, $code);

    /**
     * Called at the end of each loop.
     *
     * @param Shell $shell
     */
    public function afterLoop(Shell $shell);

    /**
     * Called once after the REPL session ends.
     *
     * @param Shell $shell
     */
    public function afterRun(Shell $shell);
}
