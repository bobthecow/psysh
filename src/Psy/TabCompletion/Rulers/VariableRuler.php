<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class VariableRuler
 * @package Psy\TabCompletion\Rulers
 */
class VariableRuler extends AbstractRuler
{
    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        return (self::tokenIs($token, self::T_VARIABLE) || self::tokenIs($token, self::T_WHITESPACE)) &&
            self::hasToken($this->allowedStartTokens, $prevToken);
    }
}
