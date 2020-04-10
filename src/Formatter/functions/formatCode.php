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

if (!\function_exists('Psy\\Formatter\\formatCode')) {
    /**
     * Format code for shell output.
     *
     * Optionally, restrict by $startLine and $endLine line numbers, or pass $markLine to add a line marker.
     *
     * @param string   $code
     * @param int      $startLine
     * @param int|null $endLine
     * @param int|null $markLine
     *
     * @return string formatted code
     */
    function formatCode($code, $startLine = 1, $endLine = null, $markLine = null)
    {
        $highlighter = new CodeHighlighter();

        return $highlighter->highlight($code, $startLine, $endLine, $markLine);
    }
}
