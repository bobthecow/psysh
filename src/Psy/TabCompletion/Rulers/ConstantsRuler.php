<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class ConstantsRuler
 * @package Psy\TabCompletion\Rulers
 */
class ConstantsRuler extends AbstractRuler
{
    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        return self::tokenIs($token, self::T_STRING) &&
            self::hasSyntax($token, self::CONSTANT_SYNTAX) &&
            self::hasToken($this->allowedStartTokens, $prevToken);
    }
}
