<?php

namespace Psy\TabCompletion;

/**
 * Class FunctionsMatcher
 * @package Psy\TabCompletion
 */
class FunctionsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches($input, $index, $info = array())
    {
        $functions = get_defined_functions();
        $allFunctions = array_merge($functions['user'], $functions['internal']);

        return array_filter($allFunctions, function ($func) use ($input) {
            return AbstractMatcher::startsWith($input, $func);
        });
    }
}
