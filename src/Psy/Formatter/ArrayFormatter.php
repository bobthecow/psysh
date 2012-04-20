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

/**
 * A pretty-printer for arrays..
 */
class ArrayFormatter
{
    public static function format(array $array)
    {
        if (empty($array)) {
            return '[]';
        }

        $formatted = array_map(array(__CLASS__, 'formatValue'), $array);
        $template  = sprintf('[%s%s%%s%s   ]', PHP_EOL, str_repeat(' ', 7), PHP_EOL);
        $glue      = sprintf(',%s%s', PHP_EOL, str_repeat(' ', 7));

        return sprintf($template, implode($glue, $formatted));
    }

    public static function formatRef(array $array)
    {
        return sprintf('Array(%d)', count($array));
    }

    public static function formatValue($value)
    {
        if (is_object($value)) {
            return ObjectFormatter::formatRef($value);
        } elseif (is_array($value)) {
            return self::formatRef($value);
        } else {
            return json_encode($value);
        }
    }
}
