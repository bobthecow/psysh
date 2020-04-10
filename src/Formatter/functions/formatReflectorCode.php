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

use Psy\Exception\RuntimeException;

if (!\function_exists('Psy\\Formatter\\formatReflectorCode')) {
    /**
     * Format the code represented by $reflector for shell output.
     *
     * @throws RuntimeException when source code is unavailable for a Reflector
     *
     * @param \Reflector  $reflector
     * @param string|null $colorMode (deprecated and ignored)
     *
     * @return string formatted code
     */
    function formatReflectorCode(\Reflector $reflector)
    {
        if (!$reflector instanceof \ReflectionClass && !$reflector instanceof \ReflectionFunctionAbstract) {
            throw new RuntimeException('Source code unavailable');
        }

        $code = @\file_get_contents($reflector->getFileName());
        if (!$code) {
            throw new RuntimeException('Source code unavailable');
        }

        $startLine = $reflector->getStartLine();
        if ($docComment = $reflector->getDocComment()) {
            $startLine -= \preg_match_all('/(\r\n?|\n)/', $docComment) + 1;
        }
        $startLine = \max($startLine, 1);

        $endLine = $reflector->getEndLine();

        $highlighter = new CodeHighlighter();

        return $highlighter->highlight($code, $startLine, $endLine);
    }
}
