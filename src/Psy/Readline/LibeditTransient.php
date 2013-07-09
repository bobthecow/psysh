<?php

namespace Psy\Readline;

use Psy\Readline\Libedit;

class LibeditTransient extends Libedit
{
    private $history;

    public static function isSupported()
    {
        return function_exists('readline');
    }

    public function __construct($historyFile = null)
    {
        parent::__construct($historyFile);
        $this->history = array();
    }

    public function addHistory($line)
    {
        $this->history[] = $line;

        return parent::addHistory($line);
    }

    public function clearHistory()
    {
        $this->history = array();

        return parent::clearHistory();
    }

    public function listHistory()
    {
        return $this->history;
    }
}
