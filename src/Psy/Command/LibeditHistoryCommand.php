<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Psy\Command\HistoryCommand;

/**
 * PsySH history fallback command for PHP with libedit instead of libreadline.
 *
 * Unlike HistoryCommand, which uses `readline_list_history`, this one fakes it
 * by reading the history file directly, and parsing it via an external call to
 * `unvis`. This is not completely ideal, but it'll do for now.
 */
class LibeditHistoryCommand extends HistoryCommand
{
    private $historyFile;

    /**
     * {@inheritdoc}
     */
    public static function isSupported()
    {
        return `which unvis` !== null;
    }

    /**
     * Set the fallback history filename, used to implement fake `list_history`
     *
     * @param string $historyFile
     */
    public function setHistoryFile($historyFile)
    {
        $this->historyFile = $historyFile;
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistory()
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
