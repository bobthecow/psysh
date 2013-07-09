<?php

namespace Psy\Readline;

use Psy\Readline\Transient;

class LibeditTransient extends Transient
{
    public static function isSupported()
    {
        return function_exists('readline');
    }

    public function readline($prompt = null)
    {
        return readline($prompt);
    }
}
