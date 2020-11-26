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
 * A class attribute tab completion Matcher.
 *
 * Given a namespace and class, this matcher provides completion for constants
 * and static properties.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class ClassAttributesMatcher extends AbstractMatcher
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

        // Second token is the nekudotayim operator '::'.
        \array_pop($tokens);

        $class = $this->getNamespaceAndClass($tokens);
        $chunks = \explode('\\', $class);
        $className = \array_pop($chunks);

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $re) {
            return [];
        }

        $vars = \array_merge(
            \array_map(
                function ($var) {
                    return '$'.$var;
                },
                \array_keys($reflection->getStaticProperties())
            ),
            \array_keys($reflection->getConstants())
        );

        // We have no control over the word-break characters used by
        // Readline's completion, and ':' isn't included in that set,
        // which means the $input which AutoCompleter::processCallback()
        // is completing includes the preceding "ClassName::" text, and
        // therefore the candidate strings we are returning must do
        // likewise.
        return \array_map(
            function ($name) use ($className) {
                return $className.'::'.$name;
            },
            \array_filter($vars, function ($var) use ($input) {
                return AbstractMatcher::startsWith($input, $var);
            })
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = \array_pop($tokens);
        $prevToken = \array_pop($tokens);

        // Valid following '::'.
        switch (true) {
            case self::tokenIs($prevToken, self::T_DOUBLE_COLON):
                return self::tokenIsValidIdentifier($token, true);
        }

        return false;
    }
}
