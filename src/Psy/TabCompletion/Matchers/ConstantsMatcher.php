<?php

namespace Psy\TabCompletion\Matchers;

/**
 * Class ConstantsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ConstantsMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $const = $this->getInput($tokens);

        return array_filter(array_keys(get_defined_constants()), function ($constant) use ($const) {
            return AbstractMatcher::startsWith($const, $constant);
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
