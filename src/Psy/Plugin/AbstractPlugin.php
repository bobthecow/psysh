<?php

namespace Psy\Plugin;

abstract class AbstractPlugin
{
    public static function register()
    {
        Manager::register(new static(), static::getName());
    }

    /**
     * @return string
     */
    final public static function getName()
    {
        $class = new \ReflectionClass(get_called_class());

        return preg_replace('#Plugin$#', '', $class->getShortName());
    }

    /**
     * @param array $configuration
     *
     * @return array
     */
    final public static function getConfiguration($configuration = array())
    {
        return array_merge_recursive(
            $configuration,
            array(
               'commands'   => static::getCommands(),
               'presenters' => static::getPresenters(),
               'matchers'   => static::getMatchers(),
                // if any more parts of the config are exposed publicly, remember to add here with the static ref.
            )
        );
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
