<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Formatter;

use JakubOnderka\PhpConsoleHighlighter\Highlighter;
use Psy\Configuration;
use Psy\ConsoleColorFactory;
use Psy\Exception\RuntimeException;

/**
 * A pretty-printer for code.
 */
class CodeFormatter implements Formatter
{
    /**
     * Format the code represented by $reflector.
     *
     * @param \Reflector  $reflector
     * @param null|string $colorMode (default: null)
     *
     * @return string formatted code
     */
    public static function format(\Reflector $reflector, $colorMode = null)
    {
        $colorMode = $colorMode ?: Configuration::COLOR_MODE_AUTO;

        if ($fileName = $reflector->getFileName()) {
            if (!is_file($fileName)) {
                throw new RuntimeException('Source code unavailable.');
            }

            $file  = file_get_contents($fileName);
            $start = $reflector->getStartLine();
            $end   = $reflector->getEndLine() - $start;

            $factory = new ConsoleColorFactory($colorMode);
            $colors = $factory->getConsoleColor();
            $highlighter = new Highlighter($colors);

            return $highlighter->getCodeSnippet($file, $start, 0, $end);

            // no need to escape this bad boy, since (for now) it's being output raw.
            // return OutputFormatter::escape(implode(PHP_EOL, $code));
            return implode(PHP_EOL, $code);
        } else {
            throw new RuntimeException('Source code unavailable.');
        }
    }
}
