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

use Psy\Reflection\ReflectionConstant_;
use Psy\Util\Json;
use Symfony\Component\Console\Formatter\OutputFormatter;

if (!\function_exists('Psy\\Formatter\\formatConstantSignature')) {
    /**
     * Format a constant signature.
     *
     * @param ReflectionConstant_ $reflector
     *
     * @return string Formatted signature
     */
    function formatConstantSignature($reflector)
    {
        $value = $reflector->getValue();
        $style = getTypeStyle($value);

        return \sprintf(
            '<keyword>define</keyword>(<string>%s</string>, <%s>%s</%s>)',
            OutputFormatter::escape(Json::encode($reflector->getName())),
            $style,
            OutputFormatter::escape(Json::encode($value)),
            $style
        );
    }
}
