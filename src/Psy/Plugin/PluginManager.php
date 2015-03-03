<?php

namespace Psy\Plugin;

class PluginManager
{
    /**
     * @var array
     */
    protected static $exposedConfigurationItems = array(
        'commands', 'matchers', 'presenters',
    );

    /** @var AbstractPlugin[] */
    protected static $plugins = array();

    /**
     * @param AbstractPlugin $plugin
     * @param $name
     *
     * @throws \Exception
     */
    public static function register(AbstractPlugin $plugin, $name)
    {
        if (array_key_exists($name, self::$plugins)) {
            throw new \Exception(
                sprintf('The plugin "%s" was already registered.', $name)
            );
        }
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
            foreach (self::$exposedConfigurationItems as $cfgBlock) {
                $getter = sprintf('get%s', ucfirst($cfgBlock));
                $cfgData = call_user_func(array($plugin, $getter));
                if (array_key_exists($cfgBlock, $configuration)) {
                    if (is_array($configuration[$cfgBlock])) {
                        // is array, let's merge
                        $configuration[$cfgBlock] = array_merge(
                            $configuration[$cfgBlock],
                            $cfgData
                        );
                    } else {
                        // not an array, it will be overwritten
                        $configuration[$cfgBlock] = $cfgData;
                    }
                } else {
                    $configuration[$cfgBlock] = $cfgData;
                }
            }
        }

        return $configuration;
    }
}
