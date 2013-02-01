<?php

namespace Psy\Readline;

use Psy\Readline as ReadlineInterface;

class Transient implements ReadlineInterface
{
    private $history;

    public static function isSupported()
    {
        return true;
    }

    public function __construct($historyFile = null)
    {
        // don't do anything with the history file...
        $this->history = array();
    }

    public function addHistory($line)
    {
        $this->history[] = $line;

        return true;
    }

    public function clearHistory()
    {
        $this->history = array();

        return true;
    }

    public function listHistory()
    {
        return $this->history;
    }

    public function readHistory()
    {
        return true;
    }

    public function readline($prompt = null)
    {
        echo $prompt;

        return rtrim(fgets(STDIN, 1024));
    }

    public function redisplay()
    {
        // noop
    }

    public function writeHistory()
    {
        return true;
    }
}
