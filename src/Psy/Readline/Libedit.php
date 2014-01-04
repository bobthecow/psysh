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
use Psy\Util\String;

/**
 * A Libedit-based Readline implementation.
 *
 * This is largely the same as the Readline implementation, but it emulates
 * support for `readline_list_history` since PHP decided it was a good idea to
 * ship a fake Readline implementation that is missing history support.
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
        return function_exists('readline');
    }

    /**
     * {@inheritDoc}
     */
    public function listHistory()
    {
        $history = file_get_contents($this->historyFile);
        if (!$history) {
            return array();
        }

        // libedit doesn't seem to support non-unix line separators. 
        $history = explode("\n", $history);

        // shift the history signature, ensure it's valid
        if ('_HiStOrY_V2_' !== array_shift($history)) {
            return array();
        }

        // decode the line
        $history = array_map(array($this, 'parseHistoryLine'), $history);
        // filter empty lines & comments 
        return array_values(array_filter($history));
    }

    /**
     * From GNUReadline (readline/histfile.c & readline/histexpand.c):
     * lines starting with "\0" are comments or timestamps;
     * if "\0" is found in an entry,
     * everything from it until the next line is a comment.
     *
     * @param string $line The history line to parse.
     *
     * @return string | null
     */ 
    protected function parseHistoryLine($line)
    {
        if (!$line) return;

        // comment or timestamp
        if ("\0" === $line[0]) return;
        // if "\0" is found in an entry, then
        // everything from it until the end of line is a comment.
        if (false !== $pos = strpos($line, "\0")) {
            $line = substr($line, 0, $pos);
        }

        return $line ? String::unvis($line) : null;
    }
    
}
