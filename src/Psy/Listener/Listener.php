<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Listener;

use Psy\Shell;

/**
 * Listener interface.
 *
 * This interface is to watch the execution loop and listen for commands.
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
     * Operations to run before the start of the loop.
     *
     * @param Shell $shell
     */
    public function beforeLoop(Shell $shell);

    /**
     * Operations to run on user input.
     *
     * @param Shell  $shell
     * @param string $input
     */
    public function onInput(Shell $shell, $input);

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
    public function afterLoop(Shell $shell);
}
