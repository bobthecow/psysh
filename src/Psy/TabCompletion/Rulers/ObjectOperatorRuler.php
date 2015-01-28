<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class ObjectOperatorRuler
 * @package Psy\TabCompletion\Rulers
 */
class ObjectOperatorRuler extends AbstractRuler
{
    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        return self::tokenIs($token, self::T_OBJECT_OPERATOR) || (
            self::tokenIs($token, self::T_STRING) && self::tokenIs($prevToken, self::T_OBJECT_OPERATOR)
        );
    }
}
