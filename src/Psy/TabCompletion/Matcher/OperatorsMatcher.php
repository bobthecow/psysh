<?php

namespace Psy\TabCompletion\Matcher;

class OperatorsMatcher extends AbstractContextAwareMatcher
{
    /**
     * Provide tab completion matches for readline input.
     *
     * @param array $tokens information substracted with get_token_all
     * @param array $info   readline_info object
     *
     * @return array The matches resulting from the query
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);
        $prevToken = array_pop($tokens);

        if (self::hasToken(array(self::T_LNUMBER, self::T_STRING), $prevToken)) {
            return str_split(self::MISC_OPERATORS);
        }

        return array($prevToken . $prevToken);
    }

    /**
     * {@inheritDoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);

        switch (true) {
            case self::tokenIs($token, self::T_LNUMBER):
            case is_string($token) && in_array($token, str_split(self::MISC_OPERATORS)):
            case self::tokenIs($token, self::T_VARIABLE) && $this->hasVariable($token[1]):
            case self::tokenIs($token, self::T_STRING) && array_key_exists($token[1], get_defined_constants()):
                return true;
        }

        return false;
    }
}
