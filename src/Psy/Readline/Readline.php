<?php

namespace Psy\Readline;

use Psy\Readline as ReadlineInterface;

class Readline implements ReadlineInterface
{
    protected $historyFile;

    public static function isSupported()
    {
        return function_exists('readline_list_history');
    }

    public function __construct($historyFile = null)
    {
        $this->historyFile = $historyFile;
    }

    public function addHistory($line)
    {
        if ($res = readline_add_history($line)) {
            $this->writeHistory();
        }

        return $res;
    }

    public function clearHistory()
    {
        if ($res = readline_clear_history()) {
            $this->writeHistory();
        }

        return $res;
    }

    public function listHistory()
    {
        return readline_list_history();
    }

    public function readHistory()
    {
        return readline_read_history($this->historyFile);
    }

    public function readline($prompt = null)
    {
        return readline($prompt);
    }

    public function redisplay()
    {
        readline_redisplay();
    }

    public function writeHistory()
    {
        return readline_write_history($this->historyFile);
    }
}
