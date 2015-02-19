<?php

namespace Psy\Test\TabCompletion;

/**
 * Class StaticSample.
 */
class StaticSample
{
    const CONSTANT_VALUE = 12;

    public static $staticVariable;

    public static function staticFunction()
    {
        return self::CONSTANT_VALUE;
    }
}
