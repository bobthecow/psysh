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
        self::T_NEW
    );

    /**
     * {@inheritDoc}
     */
    public function check($tokens)
    {

        while ($token = array_pop($tokens)) {
            if (!self::hasToken(
                array(
                    self::T_NS_SEPARATOR,
                    self::T_STRING
                ),
                $token
            )) {
                $prevToken = array_pop($tokens);
                return self::hasToken(
                    array_merge(
                        $this->allowedStartTokens,
                        $this->allowedClassTokens
                    ),
                    $prevToken
                );
            }
        }

        return false;
    }
}
