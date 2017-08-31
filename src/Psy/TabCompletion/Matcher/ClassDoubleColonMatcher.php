<?php

namespace Psy\TabCompletion\Matcher;


class ClassDoubleColonMatcher extends AbstractMatcher
{

    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $class = $this->getNamespaceAndClass($tokens);

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return array();
        }
        $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);

        if (count($methods) === 0) {
            return array();
        }

        return array($class . '::');
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);

        switch(true) {
            case self::tokenIs($token, self::T_STRING):
                return true;
        }

        return false;
    }
}