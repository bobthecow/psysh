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

use Psy\Formatter\Signature\FunctionSignatureFormatter;
use Psy\Formatter\Signature\ClassSignatureFormatter;
use Psy\Formatter\Signature\ConstantSignatureFormatter;
use Psy\Formatter\Signature\MethodSignatureFormatter;
use Psy\Formatter\Signature\PropertySignatureFormatter;

class Autographer
{
    public static function get(\Reflector $reflector)
    {
        switch (true) {
            case $reflector instanceof \ReflectionFunction:
                return new FunctionSignatureFormatter($reflector);

            case $reflector instanceof \ReflectionClass:
                return new ClassSignatureFormatter($reflector);

            case $reflector instanceof \ReflectionConstant:
                return new ConstantSignatureFormatter($reflector);

            case $reflector instanceof \ReflectionMethod:
                return new MethodSignatureFormatter($reflector);

            case $reflector instanceof \ReflectionProperty:
                return new PropertySignatureFormatter($reflector);

            default:
                throw new \InvalidArgumentException('Unexpected Reflector class: '.get_class($reflector));
        }
    }
}
