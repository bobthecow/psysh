<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

/**
 * Listener interface.
 *
 * This interface is to watch the execution loop and listen for commands.
 */
interface Listener
{
    /**
     * Determines whether this listener should be active.
     */
    public function enabled();

    /**
     * Operations to run before the start of the loop.
     *
     * @param Shell $shell
     */
    public function onBeforeLoop(Shell $shell);

    /**
     * When a command is about to be executed.
     *
     * @param Shell  $shell
     * @param string $command
     */
    public function onExecute(Shell $shell, $command);

    /**
     * Operations to run after the loop completes.
     *
     * @param Shell $shell
     */
    public function onAfterLoop(Shell $shell);
}
