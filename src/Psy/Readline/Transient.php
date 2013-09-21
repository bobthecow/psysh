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
 * An array-based Readline emulation implementation.
 */
class Transient implements Readline
{
    private $history;

    /**
     * Transient Readline is always supported.
     *
     * {@inheritDoc}
     */
    public static function isSupported()
    {
        return true;
    }

    /**
     * Transient Readline constructor.
     */
    public function __construct($historyFile = null)
    {
        // don't do anything with the history file...
        $this->history = array();
    }

    /**
     * Transient Readline is always supported.
     *
     * {@inheritDoc}
     */
    public function addHistory($line)
    {
        $this->history[] = $line;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clearHistory()
    {
        $this->history = array();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function listHistory()
    {
        return $this->history;
    }

    /**
     * {@inheritDoc}
     */
    public function readHistory()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function readline($prompt = null)
    {
        echo $prompt;

        return rtrim(fgets(STDIN, 1024));
    }

    /**
     * {@inheritDoc}
     */
    public function redisplay()
    {
        // noop
    }

    /**
     * {@inheritDoc}
     */
    public function writeHistory()
    {
        return true;
    }
}
