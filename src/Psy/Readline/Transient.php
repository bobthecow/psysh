<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline;

/**
 * An array-based Readline emulation implementation.
 */
class Transient implements Readline
{
    private $history;
    private $historySize;
    private $eraseDups;

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
    public function __construct($historyFile = null, $historySize = 0, $eraseDups = false)
    {
        // don't do anything with the history file...
        $this->history     = array();
        $this->historySize = $historySize;
        $this->eraseDups   = $eraseDups;
    }

    /**
     * {@inheritDoc}
     */
    public function addHistory($line)
    {
        if ($this->eraseDups) {
            if (($key = array_search($line, $this->history)) !== false) {
                unset($this->history[$key]);
            }
        }

        $this->history[] = $line;

        if ($this->historySize > 0) {
            $histsize = count($this->history);
            if ($histsize > $this->historySize) {
                $this->history = array_slice($this->history, $histsize - $this->historySize);
            }
        }

        $this->history = array_values($this->history);

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

        return rtrim(fgets($this->getStdin(), 1024));
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

    /**
     * Get a STDIN file handle.
     *
     * @return resource
     */
    private function getStdin()
    {
        if (!isset($this->stdin)) {
            $this->stdin = fopen('php://stdin', 'r');
        }

        return $this->stdin;
    }
}
