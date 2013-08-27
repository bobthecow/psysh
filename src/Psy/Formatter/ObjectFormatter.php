<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Formatter\RecursiveFormatter;

/**
 * A pretty-printer for object references..
 */
class ObjectFormatter extends RecursiveFormatter
{
    /**
     * Format the object.
     *
     * @param object $obj
     *
     * @return string
     */
    public static function format($obj)
    {
        $class = new \ReflectionObject($obj);
        $props = self::getProperties($obj, $class);

        return sprintf('%s %s', self::formatRef($obj), self::formatProperties($props));
    }

    /**
     * Format a reference to the object.
     *
     * @param object $obj
     *
     * @return string
     */
    public static function formatRef($obj)
    {
        return sprintf('<%s #%s>', get_class($obj), spl_object_hash($obj));
    }

    /**
     * Format object properties.
     *
     * @param array $props
     *
     * @return string
     */
    private static function formatProperties($props)
    {
        if (empty($props)) {
            return '{}';
        }

        $formatted = array();
        foreach ($props as $name => $val) {
            $formatted[] = sprintf('%s: %s', $name, self::formatValue($val));
        }

        $template = sprintf('{%s%s%%s%s   }', PHP_EOL, str_repeat(' ', 7), PHP_EOL);
        $glue     = sprintf(',%s%s', PHP_EOL, str_repeat(' ', 7));

        return sprintf($template, implode($glue, $formatted));
    }

    /**
     * Get an array of object properties.
     *
     * @param object           $obj
     * @param \ReflectionClass $class
     *
     * @return array
     */
    private static function getProperties($obj, \ReflectionClass $class)
    {
        $deprecated = false;
        $oldHandler = set_error_handler(function($errno, $errstr) use (&$deprecated) {
            if (in_array($errno, array(E_DEPRECATED, E_USER_DEPRECATED))) {
                $deprecated = true;
            } else {
                // not a deprecation error, let someone else handle this
                return false;
            }
        });

        $props = array();
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $deprecated = false;
            $val = $prop->getValue($obj);

            // TODO: maybe get the deprecated values anyway, but print them in
            // a different color to indicate that they're deprecated? orange?
            if (!$deprecated) {
                $props[$prop->getName()] = $val;
            }
        }

        set_error_handler($oldHandler);

        return $props;
    }
}
