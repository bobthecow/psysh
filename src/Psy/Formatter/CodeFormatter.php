<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use Psy\Formatter\Formatter;
use Psy\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * A pretty-printer for code.
 */
class CodeFormatter implements Formatter
{
    /**
     * Format the code represented by $reflector.
     *
     * @param \Reflector $reflector
     *
     * @return string formatted code
     */
    public static function format(\Reflector $reflector)
    {
        if ($fileName = $reflector->getFileName()) {
            if (!is_file($fileName)) {
                throw new RuntimeException('Source code unavailable.');
            }

            $file  = file_get_contents($fileName);
            $lines = preg_split('/\r?\n/', $file);

            $start = $reflector->getStartLine() - 1;
            $end   = $reflector->getEndLine() - $start;
            $code  = array_slice($lines, $start, $end);

            // no need to escape this bad boy, since (for now) it's being output raw.
            // return OutputFormatter::escape(implode(PHP_EOL, $code));
            return implode(PHP_EOL, $code);
        } else {
            throw new RuntimeException('Source code unavailable.');
        }
    }
}
