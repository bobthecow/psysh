<?php

namespace Psy\TabCompletion\Matchers;

/**
 * Class VariablesMatcher
 * @package Psy\TabCompletion\Matchers
 */
class VariablesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $var = str_replace('$', '', $this->getInput($tokens));

        return array_filter(array_keys($this->context->getAll()), function ($variable) use ($var) {
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
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::hasToken(array(self::T_OPEN_TAG, self::T_VARIABLE), $token):
            case is_string($token) && $token === '$':
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
