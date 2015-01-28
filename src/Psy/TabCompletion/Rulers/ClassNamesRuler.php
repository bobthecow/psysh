<?php

namespace Psy\TabCompletion\Rulers;

/**
 * Class ClassNamesRuler
 * @package Psy\TabCompletion\Rulers
 */
class ClassNamesRuler extends AbstractRuler
{
    /** @var array  */
    protected $allowedClassTokens = array(
        self::T_NS_SEPARATOR,
    );

    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);
        if (count($tokens) > 2) {
            $priorToken = array_pop($tokens);
            if (self::tokenIs($priorToken, self::T_NEW)) {
                return true;
            }
        }

        return self::tokenIs($token, self::T_STRING) &&
            self::hasToken(array_merge($this->allowedStartTokens, $this->allowedClassTokens), $prevToken);
    }
}
