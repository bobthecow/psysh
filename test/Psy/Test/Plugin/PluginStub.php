<?php

namespace Psy\Test\Plugin;

use Psy\Plugin\AbstractPlugin;

class PluginStub extends AbstractPlugin
{
    // due to the static nature, and the negative of Mr bergmann to audit static code
    // the data here is treated as a FIFO pile.
    protected static $matchers = array();
    protected static $presenters = array();
    protected static $commands = array();

    public function setMatchers(array $matchers)
    {
        self::$matchers[] = $matchers;
    }

    public function setPresenters(array $presenters)
    {
        self::$presenters[] = $presenters;
    }

    public function setCommands(array $commands)
    {
        self::$commands[] = $commands;
    }

    public static function getMatchers()
    {
        return array_shift(self::$matchers);
    }

    public static function getPresenters()
    {
        return array_shift(self::$presenters);
    }

    public static function getCommands()
    {
        return array_shift(self::$commands);
    }
}
