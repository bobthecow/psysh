<?php

namespace Psy\Plugin;

use Psy\Command\DemoCommand;

class DemoPlugin extends AbstractPlugin
{
    /**
     * @return array
     */
    public static function getCommands()
    {
        return array(
            new DemoCommand(),
        );
    }
}
