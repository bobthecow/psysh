<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Formatter\Formatter;
use Psy\Exception\RuntimeException;

class CodeFormatter implements Formatter
{
    public static function format(\Reflector $reflector)
    {
        if ($fileName = $reflector->getFileName()) {
            $file = file_get_contents($fileName);
            $lines = preg_split('/\r?\n/', $file);

            $start = $reflector->getStartLine() - 1;
            $end   = $reflector->getEndLine() - $start;
            $code  = array_slice($lines, $start, $end);

            return implode(PHP_EOL, $code);
        } else {
            throw new RuntimeException('Source code unavailable.');
        }
    }
}
