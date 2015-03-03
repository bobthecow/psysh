<?php

namespace Psy\Plugin;

abstract class AbstractPlugin
{
    public static function register()
    {
        PluginManager::register(new static(), static::getName());
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    public static function getName()
    {
        throw new \Exception('Missing plugin name');
    }

    // any publicly exposed configuration piece below here ↓

    /**
     * @return array
     */
    public static function getCommands()
    {
        // add your own commands
        return array();
    }

    /**
     * @return array
     */
    public static function getPresenters()
    {
        // add your own presenters
        return array();
    }

    /**
     * @return array
     */
    public static function getMatchers()
    {
        // add your own presenters
        return array();
    }
}
