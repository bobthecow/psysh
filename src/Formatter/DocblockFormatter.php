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

use Psy\Util\Docblock;

/**
 * A pretty-printer for docblocks.
 */
class DocblockFormatter implements Formatter
{
    /**
     * Format a docblock.
     *
     * @deprecated use Psy\Formatter\formatDocblock directly
     *
     * @param \Reflector $reflector
     *
     * @return string Formatted docblock
     */
    public static function format(\Reflector $reflector)
    {
        return formatDocblock(new Docblock($reflector));
    }
}
