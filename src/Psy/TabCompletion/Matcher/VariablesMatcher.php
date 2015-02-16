<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Class VariablesMatcher
 * @package Psy\TabCompletion\Matcher
 */
class VariablesMatcher extends AbstractContextAwareMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $var = str_replace('$', '', $this->getInput($tokens));

        return array_filter(array_keys($this->getVariables()), function ($variable) use ($var) {
            return AbstractMatcher::startsWith($var, $variable);
        });
    }

    /**
     * @param  array $tokens
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);

        switch (true) {
            case self::hasToken(array(self::T_OPEN_TAG, self::T_VARIABLE), $token):
            case is_string($token) && $token === '$':
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
