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

if (!\function_exists('Psy\\Formatter\\getTypeStyle')) {
    /**
     * Helper for getting output style for a given value's type.
     *
     * @internal
     *
     * @param mixed $value
     *
     * @return string
     */
    function getTypeStyle($value)
    {
        if (\is_int($value) || \is_float($value)) {
            return 'number';
        } elseif (\is_string($value)) {
            return 'string';
        } elseif (\is_bool($value) || \is_null($value)) {
            return 'bool';
        } else {
            return 'strong'; // @codeCoverageIgnore
        }
    }
}
