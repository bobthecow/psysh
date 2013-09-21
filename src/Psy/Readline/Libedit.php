<?php

namespace Psy\Readline;

use Psy\Readline\GNUReadline;

class Libedit extends GNUReadline
{
    public static function isSupported()
    {
        return function_exists('readline') && (`which unvis` !== null);
    }

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
