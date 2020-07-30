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
 * A class method tab completion Matcher.
 *
 * Given a namespace and class, this matcher provides completion for static
 * methods.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class ClassMethodsMatcher extends AbstractMatcher
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
        if (self::tokenIs($firstToken, self::T_STRING)) {
            // second token is the nekudotayim operator
            \array_pop($tokens);
        }

        $class = $this->getNamespaceAndClass($tokens);

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $re) {
            return [];
        }

        if (self::needCompleteClass($tokens[1])) {
            $methods = $reflection->getMethods();
        } else {
            $methods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
        }

        $methods = \array_map(function (\ReflectionMethod $method) {
            return $method->getName();
        }, $methods);

        // We have no control over the word-break characters used by
        // Readline's completion, and ':' isn't included in that set,
        // which means the $input which AutoCompleter::processCallback()
        // is completing includes the preceding "ClassName::" text, and
        // therefore the candidate strings we are returning must do
        // likewise.
        return \array_map(
            function ($name) use ($class) {
                $chunks = \explode('\\', $class);
                $className = \array_pop($chunks);

                return $className.'::'.$name;
            },
            \array_filter($methods, function ($method) use ($input) {
                return AbstractMatcher::startsWith($input, $method);
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

        switch (true) {
            case self::tokenIs($prevToken, self::T_DOUBLE_COLON) && self::tokenIs($token, self::T_STRING):
            case self::tokenIs($token, self::T_DOUBLE_COLON):
                return true;
        }

        return false;
    }
}
