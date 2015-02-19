<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

/**
 * An object attribute tab completion Matcher.
 *
 * This matcher provides completion for properties of objects in the current
 * Context.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class ObjectAttributesMatcher extends AbstractContextAwareMatcher
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
            array_keys(get_class_vars(get_class($object))),
            function ($var) use ($input) {
                return AbstractMatcher::startsWith($input, $var);
            }
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
            case self::tokenIs($token, self::T_OBJECT_OPERATOR):
            case self::tokenIs($prevToken, self::T_OBJECT_OPERATOR):
                return true;
        }

        return false;
    }
}
