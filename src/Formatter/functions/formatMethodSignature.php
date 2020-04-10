<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

if (!\function_exists('Psy\\Formatter\\formatMethodSignature')) {
    /**
     * Format a method signature.
     *
     * @param \ReflectionMethod $reflector
     *
     * @return string Formatted signature
     */
    function formatMethodSignature(\ReflectionMethod $reflector)
    {
        $modifiers = \implode(' ', \array_map(function ($modifier) {
            return \sprintf('<keyword>%s</keyword>', $modifier);
        }, \Reflection::getModifierNames($reflector->getModifiers())));

        return \sprintf('%s %s', $modifiers, formatFunctionSignature($reflector));
    }
}
