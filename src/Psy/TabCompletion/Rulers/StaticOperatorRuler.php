<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class StaticOperatorRuler
 * @package Psy\TabCompletion\Rulers
 */
class StaticOperatorRuler extends AbstractRuler
{
    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        return self::tokenIs($token, self::T_DOUBLE_COLON) || (
            self::tokenIs($token, self::T_STRING) && self::tokenIs($prevToken, self::T_DOUBLE_COLON)
        );
    }
}
