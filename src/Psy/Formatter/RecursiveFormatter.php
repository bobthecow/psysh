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

use Psy\Exception\FatalErrorException;
use Psy\Formatter\ArrayFormatter;
use Psy\Formatter\ObjectFormatter;

/**
 * A pretty-printer for recursive objects and arrays references..
 */
abstract class RecursiveFormatter
{
    /**
     * Recursively format an object or array.
     *
     * @param mixed $obj
     *
     * @return string
     */
    public static function format($obj)
    {
        throw new FatalErrorException('format should be implemented by extending classes.');
    }

    /**
     * Format a reference to an object or array.
     *
     * @param mixed $obj
     *
     * @return string
     */
    public static function formatRef($obj)
    {
        throw new FatalErrorException('formatRef should be implemented by extending classes.');
    }

    /**
     * Helper function for formatting properties recursively.
     *
     * @param mixed $val Object, array or primitive value.
     *
     * @return string
     */
    public static function formatValue($val)
    {
        if (is_object($val)) {
            return ObjectFormatter::formatRef($val);
        } elseif (is_array($val)) {
            return ArrayFormatter::formatRef($val);
        } else {
            return json_encode($val);
        }
    }
}
