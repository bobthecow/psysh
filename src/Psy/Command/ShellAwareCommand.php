<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\ShellAware;

class ShellAwareCommand extends Command implements ShellAware
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
