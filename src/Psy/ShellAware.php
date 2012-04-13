<?php

namespace Psy;

use Psy\Shell;

interface ShellAware
{
    public function setShell(Shell $shell);
}
