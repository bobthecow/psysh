<?php

namespace Psy\Util;

use Psy\Exception\RuntimeException;
use Psy\Util\Docblock;

/**
 * A general purpose value inspector.
 */
class Inspector
{
    const CONSTANTS    = 1;
    const METHODS      = 2;
    const PROPERTIES   = 4;

    const IS_PUBLIC    = 8;
    const IS_PRIVATE   = 16;
    const IS_PROTECTED = 32;

    public static function getConstants($value)
    {
        $class = self::getReflectionClass($value);

        return array_map(function($e) { return 'public'; }, $class->getConstants());
    }

    public static function getMethods($value, $verbose = false)
    {
        $class   = self::getReflectionClass($value);
        $methods = array();
        foreach ($class->getMethods() as $method) {
            if ($verbose || $method->isPublic()) {
                $methods[$method->name] = self::getVisibility($method);
            }
        }

        return $methods;
    }


    public static function getProperties($value, $verbose = false, $includeInstanceVars = false)
    {
        $class = self::getReflectionClass($value);

        if ($includeInstanceVars && !is_object($value)) {
            throw new \InvalidArgumentException('Unable to inspect instance variables without an instance');
        }


        $props = array();
        foreach ($class->getProperties() as $property) {
            if ($verbose || $property->isPublic()) {
                $props[$property->name] = self::getVisibility($property);
            }
        }

        if ($includeInstanceVars) {
            if (!is_object($value)) {
                throw new \InvalidArgumentException('Unable to inspect instance variables without an instance');
            }

            foreach (self::getInstanceVars($value) as $var) {
                if (!isset($props[$var])) {
                    $props[$var] = 'instance';
                }
            }
        }

        return $props;
    }

    public static function getReflectionClass($value)
    {
        if (!is_object($value)) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Inspector expects an object or class');
            } elseif (!class_exists($value) && !interface_exists($value)) {
                throw new \InvalidArgumentException('Unknown class: '.$value);
            }
        }

        return new \ReflectionClass($value);
    }

    private static function getVisibility($prop)
    {
        if ($prop->isProtected()) {
            return self::IS_PROTECTED;
        } elseif ($prop->isPrivate()) {
            return self::IS_PRIVATE;
        } else {
            return self::IS_PUBLIC;
        }
    }

    /**
     * Fake a way to find instance variable names.
     *
     * This is ugly, but the Reflection APIs don't provide a way to do this,
     * so it'll have to do :)
     *
     * @param object $value
     *
     * @return array Instance variable names.
     */
    private static function getInstanceVars($value)
    {
        return array_keys(json_decode(json_encode($value), true));
    }
}
