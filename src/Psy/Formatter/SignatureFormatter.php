<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Formatter\Formatter;
use Psy\Reflection\ReflectionConstant;

/**
 * An abstract representation of a function, class or property signature.
 */
class SignatureFormatter implements Formatter
{
    /**
     * Format a signature for the given reflector.
     *
     * Defers to subclasses to do the actual formatting.
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted signature.
     */
    public static function format(\Reflector $reflector)
    {
        switch (true) {
            case $reflector instanceof \ReflectionFunction:
                return self::formatFunction($reflector);

            // this case also covers \ReflectionObject:
            case $reflector instanceof \ReflectionClass:
                return self::formatClass($reflector);

            case $reflector instanceof ReflectionConstant:
                return self::formatConstant($reflector);

            case $reflector instanceof \ReflectionMethod:
                return self::formatMethod($reflector);

            case $reflector instanceof \ReflectionProperty:
                return self::formatProperty($reflector);

            default:
                throw new \InvalidArgumentException('Unexpected Reflector class: '.get_class($reflector));
        }
    }

    /**
     * Print the signature name.
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted name.
     */
    public static function formatName(\Reflector $reflector)
    {
        return $reflector->getName();
    }

    /**
     * Print the method, property or class modifiers.
     *
     * Techinically this should be a trait. Can't wait for 5.4 :)
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted modifiers.
     */
    private static function formatModifiers(\Reflector $reflector)
    {
        return implode(' ', array_map(function($modifier) {
            return sprintf('<comment>%s</comment>', $modifier);
        }, \Reflection::getModifierNames($reflector->getModifiers())));
    }

    /**
     * Format a class signature.
     *
     * @param \ReflectionClass $reflector
     *
     * @return string Formatted signature.
     */
    private static function formatClass(\ReflectionClass $reflector)
    {
        $chunks = array();

        if ($modifiers = self::formatModifiers($reflector)) {
            $chunks[] = $modifiers;
        }

        if (version_compare(PHP_VERSION, '5.4', '>=') && $reflector->isTrait()) {
            $chunks[] = 'trait';
        } else {
            $chunks[] = $reflector->isInterface() ? 'interface' : 'class';
        }

        $chunks[] = sprintf('<info>%s</info>', self::formatName($reflector));

        if ($parent = $reflector->getParentClass()) {
            $chunks[] = 'extends';
            $chunks[] = sprintf('<info>%s</info>', $parent->getName());
        }

        $interfaces = $reflector->getInterfaceNames();
        if (!empty($interfaces)) {
            $chunks[] = 'implements';
            $chunks[] = implode(', ', array_map(function($name) {
                return sprintf('<info>%s</info>', $name);
            }, $interfaces));
        }

        return implode(' ', $chunks);
    }

    /**
     * Format a constant signature.
     *
     * @param ReflectionConstant $reflector
     *
     * @return string Formatted signature.
     */
    private static function formatConstant(ReflectionConstant $reflector)
    {
        return sprintf(
            '<info>const</info> <strong>%s</strong> = <return>%s</return>',
            self::formatName($reflector),
            json_encode($reflector->getValue())
        );
    }

    /**
     * Format a property signature.
     *
     * @param \ReflectionProperty $reflector
     *
     * @return string Formatted signature.
     */
    private static function formatProperty(\ReflectionProperty $reflector)
    {
        return sprintf(
            '%s <strong>$%s</strong>',
            self::formatModifiers($reflector),
            $reflector->getName()
        );
    }

    /**
     * Format a function signature.
     *
     * @param \ReflectionFunction $reflector
     *
     * @return string Formatted signature.
     */
    private static function formatFunction(\ReflectionFunctionAbstract $reflector)
    {
        return sprintf(
            '<info>function</info> %s<strong>%s</strong>(%s)',
            $reflector->returnsReference() ? '&' : '',
            self::formatName($reflector),
            implode(', ', self::formatFunctionParams($reflector))
        );
    }

    /**
     * Format a method signature.
     *
     * @param \ReflectionMethod $reflector
     *
     * @return string Formatted signature.
     */
    private static function formatMethod(\ReflectionMethod $reflector)
    {
        return sprintf(
            '<info>%s</info> %s',
            self::formatModifiers($reflector),
            self::formatFunction($reflector)
        );
    }

    /**
     * Print the function params.
     *
     * @param \ReflectionFunctionAbstract $reflector
     *
     * @return string
     */
    private static function formatFunctionParams(\ReflectionFunctionAbstract $reflector)
    {
        $params = array();
        foreach ($reflector->getParameters() as $param) {
            if ($param->isArray()) {
                $hint = '<info>array</info> ';
            } elseif ($class = $param->getClass()) {
                $hint = sprintf('<info>%s</info> ', $class->getName());
            } else {
                $hint = '';
            }

            if ($param->isOptional()) {
                if (!$param->isDefaultValueAvailable()) {
                    $value = 'unknown';
                } else {
                    $value = $param->getDefaultValue();
                    $value = is_array($value) ? 'array()' : is_null($value) ? 'null' : var_export($value, true);
                }
                $default = sprintf(' = <return>%s</return>', $value);
            } else {
                $default = '';
            }

            $params[] = sprintf(
                '%s%s<strong>$%s</strong>%s',
                $param->isPassedByReference() ? '&' : '',
                $hint,
                $param->getName(),
                $default
            );
        }

        return $params;
    }
}
