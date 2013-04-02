<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\Command\ShellAware;

/**
 * An interface for shell-aware Commands.
 *
 * The Psy Shell is automatically injected into all shell-aware commands.
 */
abstract class ShellAwareCommand extends Command implements ShellAware
{
    /**
     * Shell instance (for ShellAware interface)
     *
     * @type Psy\Shell
     */
    protected $shell;

    /**
     * ShellAware interface.
     *
     * @param Psy\Shell $shell
     */
    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }
}
