<?php

namespace Psy\TabCompletion\Matchers;

/**
 * Class FunctionsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class FunctionsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $func = $this->getInput($tokens);

        $functions = get_defined_functions();
        $allFunctions = array_merge($functions['user'], $functions['internal']);

        return array_filter($allFunctions, function ($function) use ($func) {
            return AbstractMatcher::startsWith($func, $function);
        });
    }

    /**
     * @param  array $tokens
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::tokenIs($prevToken, self::T_NEW):
                return false;
            case self::hasToken(array(self::T_OPEN_TAG, self::T_STRING), $token):
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
