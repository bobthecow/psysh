<?php

namespace Psy\TabCompletion\Matcher;

/**
 * A class name tab completion Matcher.
 *
 * This matcher provides completion for all declared classes.
 */
class ClassNamesMatcher extends AbstractMatcher
{
    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $class = $this->getNamespaceAndClass($tokens);
        if (strlen($class) > 0 && $class[0] === '\\') {
            $class = substr($class, 1, strlen($class));
        }
        $quotedClass = preg_quote($class);

        return array_map(
            function ($className) use ($class) {
                // get the number of namespace separators
                $nsPos = substr_count($class, '\\');
                $pieces = explode('\\', $className);
                //$methods = Mirror::get($class);
                return implode('\\', array_slice($pieces, $nsPos, count($pieces)));
            },
            array_filter(
                get_declared_classes(),
                function ($className) use ($quotedClass) {
                    return AbstractMatcher::startsWith($quotedClass, $className);
                }
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::hasToken(array(self::T_NEW, self::T_OPEN_TAG, self::T_NS_SEPARATOR), $prevToken):
            case self::hasToken(array(self::T_OPEN_TAG, self::T_VARIABLE), $token):
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
