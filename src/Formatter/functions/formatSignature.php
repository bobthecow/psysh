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

use Psy\Reflection\ReflectionClassConstant;
use Psy\Reflection\ReflectionConstant_;

if (!\function_exists('Psy\\Formatter\\formatSignature')) {
    /**
     * Format a signature for the given reflector.
     *
     * Defers to subclasses to do the actual formatting.
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted signature
     */
    function formatSignature(\Reflector $reflector)
    {
        switch (true) {
            case $reflector instanceof \ReflectionFunction:
            case $reflector instanceof ReflectionLanguageConstruct:
                return formatFunctionSignature($reflector);

            // this case also covers \ReflectionObject:
            case $reflector instanceof \ReflectionClass:
                return formatClassSignature($reflector);

            case $reflector instanceof ReflectionClassConstant:
            case $reflector instanceof \ReflectionClassConstant:
                return formatClassConstantSignature($reflector);

            case $reflector instanceof \ReflectionMethod:
                return formatMethodSignature($reflector);

            case $reflector instanceof \ReflectionProperty:
                return formatPropertySignature($reflector);

            case $reflector instanceof ReflectionConstant_:
                return formatConstantSignature($reflector);

            default:
                throw new \InvalidArgumentException('Unexpected Reflector class: ' . \get_class($reflector));
        }
    }
}
