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

use Psy\Formatter\RecursiveFormatter;

/**
 * A pretty-printer for arrays..
 */
class ArrayFormatter extends RecursiveFormatter
{
    /**
     * Format the array.
     *
     * @param array $array
     *
     * @return string
     */
    public static function format($array)
    {
        if (empty($array)) {
            return '[]';
        }

        $formatted = array_map(array(__CLASS__, 'formatValue'), $array);

        $keys = array_keys($array);
        if (! is_int(reset($keys))) {
            $pad = max(array_map('strlen', $keys)) + 2;
            array_walk($formatted, array(__CLASS__, 'formatKeys'), $pad);
        }

        $template  = sprintf('[%s%s%%s%s   ]', PHP_EOL, str_repeat(' ', 7), PHP_EOL);
        $glue      = sprintf(',%s%s', PHP_EOL, str_repeat(' ', 7));

        return sprintf($template, implode($glue, $formatted));
    }

    /**
     * Format a reference to the array.
     *
     * @param array $array
     *
     * @return string
     */
    public static function formatRef($array)
    {
        return sprintf('Array(%d)', count($array));
    }

    public static function formatKeys(&$value, $key, $pad = 0)
    {
        $value = sprintf("%-${pad}s => %s", json_encode($key), $value);
    }
}
