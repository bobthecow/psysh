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

use JakubOnderka\PhpConsoleColor\ConsoleColor;
use JakubOnderka\PhpConsoleHighlighter\Highlighter;
use Psy\Exception\RuntimeException;

/**
 * A pretty-printer for code.
 */
class CodeFormatter implements Formatter
{
    /**
     * Format the code represented by $reflector.
     *
     * @param \Reflector $reflector
     * @param bool       $forceColor (default: false)
     *
     * @return string formatted code
     */
    public static function format(\Reflector $reflector, $forceColor = false)
    {
        if ($fileName = $reflector->getFileName()) {
            if (!is_file($fileName)) {
                throw new RuntimeException('Source code unavailable.');
            }

            $file  = file_get_contents($fileName);
            $start = $reflector->getStartLine();
            $end   = $reflector->getEndLine() - $start;

            $colors = new ConsoleColor();
            $colors->addTheme('line_number', array('blue'));
            $colors->setForceStyle($forceColor);
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
