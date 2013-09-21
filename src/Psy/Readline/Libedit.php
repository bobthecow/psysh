<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline;

use Psy\Readline\GNUReadline;

/**
 * A Libedit-based Readline implementation.
 *
 * This is largely the same as the Readline implementation, but it emulates
 * support for `readline_list_history` since PHP decided it was a good idea to
 * ship a fake Readline implementation that is missing history support.
 *
 * Note that this implementation relies on the `unvis` binary to parse the
 * Libedit history file. Without that, PsySH will fall back to LibeditTransient.
 */
class Libedit extends GNUReadline
{
    /**
     * If `unvis` is available, we can emulate GNU Readline by manually reading
     * and parsing the history file. Without it, we're pretty much outta luck.
     *
     * @return boolean
     */
    public static function isSupported()
    {
        return function_exists('readline') && (`which unvis` !== null);
    }

    /**
     * {@inheritDoc}
     */
    public function listHistory()
    {
        $history = array();
        $file = escapeshellarg($this->historyFile);
        exec("unvis $file", $history);

        // shift the history signature, ensure it's valid
        if (array_shift($history) !== '_HiStOrY_V2_') {
            return array();
        }

        return $history;
    }
}
