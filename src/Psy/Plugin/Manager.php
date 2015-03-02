<?php

namespace Psy\Plugin;

class Manager
{
    /** @var AbstractPlugin[] */
    protected static $plugins = array();

    /**
     * @param AbstractPlugin $plugin
     * @param $name
     */
    public static function register(AbstractPlugin $plugin, $name)
    {
        self::$plugins[$name] = $plugin;
    }

    /**
     * @param array $configuration
     *
     * @return array
     */
    public static function getConfiguration($configuration = array())
    {
        foreach (self::$plugins as $plugin) {
            $configuration = $plugin::getConfiguration($configuration);
        }

        return $configuration;
    }
}
