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

use Psy\Util\Json;
use Symfony\Component\Console\Formatter\OutputFormatter;

if (!\function_exists('Psy\\Formatter\\formatClassConstantSignature')) {
    /**
     * Format a constant signature.
     *
     * @param ReflectionClassConstant|\ReflectionClassConstant $reflector
     *
     * @return string Formatted signature
     */
    function formatClassConstantSignature($reflector)
    {
        $value = $reflector->getValue();
        $style = getTypeStyle($value);

        return \sprintf(
            '<keyword>const</keyword> <const>%s</const> = <%s>%s</%s>',
            $reflector->getName(),
            $style,
            OutputFormatter::escape(Json::encode($value)),
            $style
        );
    }
}
