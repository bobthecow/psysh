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

use Psy\Readline\Libedit;

/**
 * Transient Libedit is a Libedit-based readline implementation, but with an
 * array-backed history fallback, since PHP's Libedit implementation lacks basic
 * things like `readline_list_history`.
 *
 * Ugh.
 */
class LibeditTransient extends Libedit
{
    private $history;

    /**
     * {@inheritDoc}
     */
    public static function isSupported()
    {
        return function_exists('readline');
    }

    /**
     * {@inheritDoc}
     */
    public function __construct($historyFile = null)
    {
        parent::__construct($historyFile);
        $this->history = array();
    }

    /**
     * {@inheritDoc}
     */
    public function addHistory($line)
    {
        $this->history[] = $line;

        return parent::addHistory($line);
    }

    /**
     * {@inheritDoc}
     */
    public function clearHistory()
    {
        $this->history = array();

        return parent::clearHistory();
    }

    /**
     * {@inheritDoc}
     */
    public function listHistory()
    {
        return $this->history;
    }
}
