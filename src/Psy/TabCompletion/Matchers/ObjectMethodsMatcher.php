<?php

namespace Psy\TabCompletion\Matchers;

/**
 * Class ObjectMethodsMatcher
 * @package Psy\TabCompletion\Matchers
 */
class ObjectMethodsMatcher extends AbstractContextAwareMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        $firstToken = array_pop($tokens);
        if (self::tokenIs($firstToken, self::T_STRING)) {
            // second token is the object operator
            array_pop($tokens);
        }
        $objectToken = array_pop($tokens);
        $objectName = str_replace('$', '', $objectToken[1]);
        $object = $this->getVariable($objectName);

        return array_filter(
            get_class_methods($object),
            function ($var) use ($input) {
                return AbstractMatcher::startsWith($input, $var);
            }
        );
    }

    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::tokenIs($token, self::T_OBJECT_OPERATOR):
            case self::tokenIs($prevToken, self::T_OBJECT_OPERATOR):
                return true;
        }

        return false;
    }
}
