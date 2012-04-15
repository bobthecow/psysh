<?php

namespace Psy\Formatter;

use Psy\Formatter\Formatter;

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
            throw new \RuntimeException('Code not found.');
        }
    }
}
