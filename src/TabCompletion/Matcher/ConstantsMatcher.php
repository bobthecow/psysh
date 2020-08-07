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
 * A constant name tab completion Matcher.
 *
 * This matcher provides completion for all defined constants.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class ConstantsMatcher extends AbstractMatcher
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

        return \array_filter(\array_keys(\get_defined_constants()), function ($constant) use ($input) {
            return AbstractMatcher::startsWith($input, $constant);
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
            case self::tokenIsValidIdentifier($token, true);
                return true;
        }

        return false;
    }
}
