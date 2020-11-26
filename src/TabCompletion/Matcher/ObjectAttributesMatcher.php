<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

use InvalidArgumentException;

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
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = [])
    {
        $input = $this->getInput($tokens);
        if ($input === false) {
            return [];
        }

        $firstToken = \array_pop($tokens);

        // Second token is the object operator '->'.
        \array_pop($tokens);

        $objectToken = \array_pop($tokens);
        if (!\is_array($objectToken)) {
            return [];
        }
        $objectName = \str_replace('$', '', $objectToken[1]);

        try {
            $object = $this->getVariable($objectName);
        } catch (InvalidArgumentException $e) {
            return [];
        }

        if (!\is_object($object)) {
            return [];
        }

        return \array_filter(
            \array_keys(\get_class_vars(\get_class($object))),
            function ($var) use ($input) {
                return AbstractMatcher::startsWith($input, $var);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = \array_pop($tokens);
        $prevToken = \array_pop($tokens);

        // Valid following '->'.
        switch (true) {
            case self::tokenIs($prevToken, self::T_OBJECT_OPERATOR):
                return self::tokenIsValidIdentifier($token, true);
        }

        return false;
    }
}
