<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Class ClassAttributesMatcher
 * @package Psy\TabCompletion\Matcher
 */
class ClassAttributesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        $firstToken = array_pop($tokens);
        if (self::tokenIs($firstToken, self::T_STRING)) {
            // second token is the nekudotayim operator
            array_pop($tokens);
        }

        $class = $this->getNamespaceAndClass($tokens);

        $reflection = new \ReflectionClass($class);
        $vars = array_merge(
            array_map(
                function ($var) {
                    return '$' . $var;
                },
                array_keys($reflection->getStaticProperties())
            ),
            array_keys($reflection->getConstants())
        );

        return array_map(
            function ($name) use ($class) {
                return $class . '::' . $name;
            },
            array_filter(
                $vars,
                function ($var) use ($input) {
                    return AbstractMatcher::startsWith($input, $var);
                }
            )
        );
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
            case self::tokenIs($prevToken, self::T_DOUBLE_COLON) && self::tokenIs($token, self::T_STRING):
            case self::tokenIs($token, self::T_DOUBLE_COLON):
                return true;
        }

        return false;
    }
}
