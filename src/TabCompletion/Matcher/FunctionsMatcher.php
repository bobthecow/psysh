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

/**
 * A function name tab completion Matcher.
 *
 * This matcher provides completion for all internal and user-defined functions.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class FunctionsMatcher extends AbstractMatcher
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

        $functions = \get_defined_functions();
        $allFunctions = \array_merge($functions['user'], $functions['internal']);

        return \array_filter($allFunctions, function ($function) use ($input) {
            return AbstractMatcher::startsWith($input, $function);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = \array_pop($tokens);
        $prevToken = \array_pop($tokens);
        $prevTokenBlacklist = [
            self::T_NEW,
            self::T_NS_SEPARATOR,
            self::T_OBJECT_OPERATOR,
            self::T_DOUBLE_COLON,
        ];

        switch (true) {
            // Previous token (blacklist).
            case self::hasToken($prevTokenBlacklist, $prevToken):
                return false;
            // Current token (whitelist).
            case self::tokenIsValidIdentifier($token, true):
                return true;
        }

        return false;
    }
}
