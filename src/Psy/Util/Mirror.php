<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Util;

use Psy\Exception\RuntimeException;
use Psy\Reflection\ReflectionConstant;

/**
 * Utility class for getting Reflectors.
 */
class Mirror
{
    const CONSTANT        = 1;
    const METHOD          = 2;
    const STATIC_PROPERTY = 4;
    const PROPERTY        = 8;

    /**
     * Get a Reflector for a function, class or instance, constant, method or property.
     *
     * Optionally, pass a $filter param to restrict the types of members checked. For example, to only Reflectors for
     * static properties and constants, pass:
     *
     *    $filter = Mirror::CONSTANT | Mirror::STATIC_PROPERTY
     *
     * @throws \Psy\Exception\RuntimeException when a $member specified but not present on $value.
     * @throws \InvalidArgumentException if $value is something other than an object or class/function name.
     *
     * @param mixed  $value  Class or function name, or variable instance.
     * @param string $member Optional: property, constant or method name (default: null)
     * @param int    $filter (default: CONSTANT | METHOD | PROPERTY | STATIC_PROPERTY)
     *
     * @return Reflector
     */
    public static function get($value, $member = null, $filter = 15)
    {
        if ($member === null && is_string($value) && function_exists($value)) {
            return self::getFunction($value);
        }

        $class = self::getClass($value);

        if ($member === null) {
            return $class;
        } elseif ($filter & self::CONSTANT && $class->hasConstant($member)) {
            return self::getConstant($class, $member);
        } elseif ($filter & self::METHOD && $class->hasMethod($member)) {
            return self::getMethod($class, $member);
        } elseif ($filter & self::PROPERTY && $class->hasProperty($member)) {
            return self::getProperty($class, $member);
        } elseif ($filter & self::STATIC_PROPERTY && $class->hasProperty($member) && $class->getProperty($member)->isStatic()) {
            return self::getProperty($class, $member);
        } else {
            throw new RuntimeException(sprintf(
                'Unknown member %s on class %s',
                $member,
                is_object($value) ? get_class($value) : $value
            ));
        }
    }

    public static function getFunction($value)
    {
        return new \ReflectionFunction($value);
    }

    public static function getClass($value)
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

    public static function getConstant(\ReflectionClass $class, $member)
    {
        return new ReflectionConstant($class, $member);
    }

    public static function getMethod(\ReflectionClass $class, $member)
    {
        return $class->getMethod($member);
    }

    public static function getProperty(\ReflectionClass $class, $member)
    {
        return $class->getProperty($member);
    }
}
