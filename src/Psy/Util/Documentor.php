<?php

namespace Psy\Util;

use Psy\Exception\RuntimeException;
use Psy\Util\Docblock;
use Psy\Util\Signature\ClassSignature;
use Psy\Util\Signature\ConstantSignature;
use Psy\Util\Signature\FunctionSignature;
use Psy\Util\Signature\MethodSignature;
use Psy\Util\Signature\PropertySignature;

/**
 * Utility class for generating code signatures and documentation.
 */
class Documentor
{
    const CONSTANT        = 1;
    const METHOD          = 2;
    const STATIC_PROPERTY = 4;
    const PROPERTY        = 8;

    /**
     * Get a code signature and documentation for a function, class or instance, constant, method or property.
     *
     * Optionally, pass a $filter param to restrict the types of members checked. For example, to only return
     * documentation for static properties and constants, pass:
     *
     *    $filter = Documentor::CONSTANT | Documentor::STATIC_PROPERTY
     *
     * @throws \Psy\Exception\RuntimeException when a $member specified but not present on $value.
     * @throws \InvalidArgumentException if $value is something other than an object or class/function name.
     *
     * @param mixed  $value  Class or instance
     * @param string $member Optional: property, constant or method name (default: null)
     * @param int    $filter (default: CONSTANT | METHOD | PROPERTY | STATIC_PROPERTY)
     *
     * @return array (Signature, Docblock)
     */
    public static function get($value, $member = null, $filter = 15)
    {
        if ($member === null && is_string($value) && function_exists($value)) {
            return self::getFunctionDoc($value);
        }

        $class = self::getReflectionClass($value);

        if ($member === null) {
            return self::getReflectionClassDoc($class);
        } elseif ($filter & self::CONSTANT && $class->hasConstant($member)) {
            return self::getConstantDoc($class, $member);
        } elseif ($filter & self::METHOD && $class->hasMethod($member)) {
            return self::getMethodDoc($class, $member);
        } elseif ($filter & self::PROPERTY && $class->hasProperty($member)) {
            return self::getPropertyDoc($class, $member);
        } elseif ($filter & self::STATIC_PROPERTY && $class->hasProperty($member) && $class->getProperty($member)->isStatic()) {
            return self::getPropertyDoc($class, $member);
        } else {
            throw new RuntimeException(sprintf(
                'Unknown member %s on class %s',
                $member,
                is_object($value) ? get_class($value) : $value
            ));
        }
    }

    private static function getFunctionDoc($value)
    {
        $func = new \ReflectionFunction($value);

        return array(new FunctionSignature($func), new Docblock($func->getDocComment()));
    }

    private static function getReflectionClassDoc(\ReflectionClass $class)
    {
        return array(new ClassSignature($class), new Docblock($class->getDocComment()));
    }

    private static function getConstantDoc(\ReflectionClass $class, $member)
    {
        $constant = $class->getConstant($member);

        return array(new ConstantSignature($constant), new Docblock($constant->getDocComment()));
    }

    private static function getMethodDoc(\ReflectionClass $class, $member)
    {
        $method = $class->getMethod($member);

        return array(new MethodSignature($method), new Docblock($method->getDocComment()));
    }

    private static function getPropertyDoc(\ReflectionClass $class, $member)
    {
        $property = $class->getProperty($member);

        return array(new PropertySignature($property), new Docblock($property->getDocComment()));
    }

    private static function getReflectionClass($value)
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
}
