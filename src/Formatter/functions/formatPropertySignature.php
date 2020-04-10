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

if (!\function_exists('Psy\\Formatter\\formatPropertySignature')) {
    /**
     * Format a property signature.
     *
     * @param \ReflectionProperty $reflector
     *
     * @return string Formatted signature
     */
    function formatPropertySignature(\ReflectionProperty $reflector)
    {
        $modifiers = \implode(' ', \array_map(function ($modifier) {
            return \sprintf('<keyword>%s</keyword>', $modifier);
        }, \Reflection::getModifierNames($reflector->getModifiers())));

        return \sprintf('%s <strong>$%s</strong>', $modifiers, $reflector->getName());
    }
}
