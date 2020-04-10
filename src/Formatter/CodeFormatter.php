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

/**
 * A pretty-printer for code.
 */
class CodeFormatter implements Formatter
{
    /**
     * Format the code represented by $reflector for shell output.
     *
     * @deprecated use Psy\Formatter\formatReflectorCode directly
     *
     * @param \Reflector  $reflector
     * @param string|null $colorMode (deprecated and ignored)
     *
     * @return string formatted code
     */
    public static function format(\Reflector $reflector, $colorMode = null)
    {
        return formatReflectorCode($reflector);
    }

    /**
     * Format code for shell output.
     *
     * Optionally, restrict by $startLine and $endLine line numbers, or pass $markLine to add a line marker.
     *
     * @deprecated use Psy\Formatter\formatCode directly
     *
     * @param string   $code
     * @param int      $startLine
     * @param int|null $endLine
     * @param int|null $markLine
     *
     * @return string formatted code
     */
    public static function formatCode($code, $startLine = 1, $endLine = null, $markLine = null)
    {
        return formatCode($code, $startLine, $endLine, $markLine);
    }
}
