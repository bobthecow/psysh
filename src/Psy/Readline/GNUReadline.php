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

use Psy\Readline\Readline;

/**
 * A Readline interface implementation for GNU Readline.
 *
 * This is by far the coolest way to do it, but it doesn't work with new PHP.
 *
 * Oh well.
 */
class GNUReadline implements Readline
{
    protected $historyFile;

    /**
     * GNU Readline is supported iff `readline_list_history` is defined. PHP
     * decided it would be awesome to swap out GNU Readline for Libedit, but
     * they ended up shipping an incomplete implementation. So we've got this.
     *
     * @return bool
     */
    public static function isSupported()
    {
        return function_exists('readline_list_history');
    }

    /**
     * GNU Readline constructor.
     */
    public function __construct($historyFile = null)
    {
        $this->historyFile = $historyFile;
    }

    /**
     * {@inheritDoc}
     */
    public function addHistory($line)
    {
        if ($res = readline_add_history($line)) {
            $this->writeHistory();
        }

        return $res;
    }

    /**
     * {@inheritDoc}
     */
    public function clearHistory()
    {
        if ($res = readline_clear_history()) {
            $this->writeHistory();
        }

        return $res;
    }

    /**
     * {@inheritDoc}
     */
    public function listHistory()
    {
        return readline_list_history();
    }

    /**
     * {@inheritDoc}
     */
    public function readHistory()
    {
        return readline_read_history($this->historyFile);
    }

    /**
     * {@inheritDoc}
     */
    public function readline($prompt = null)
    {
        return readline($prompt);
    }

    /**
     * {@inheritDoc}
     */
    public function redisplay()
    {
        readline_redisplay();
    }

    /**
     * {@inheritDoc}
     */
    public function writeHistory()
    {
        return readline_write_history($this->historyFile);
    }
}
