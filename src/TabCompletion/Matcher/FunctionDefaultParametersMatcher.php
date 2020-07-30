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
 * A function parameter tab completion Matcher.
 *
 * This provides completions for all parameters of the specifed function.
 */
class FunctionDefaultParametersMatcher extends AbstractDefaultParametersMatcher
{
    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = [])
    {
        \array_pop($tokens); // open bracket

        $functionName = \array_pop($tokens);

        try {
            $reflection = new \ReflectionFunction($functionName[1]);
        } catch (\ReflectionException $e) {
            return [];
        }

        $parameters = $reflection->getParameters();

        return $this->getDefaultParameterCompletion($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        // Valid following 'FUNCTION('.
        $openBracket = \array_pop($tokens);

        if ($openBracket !== '(') {
            return false;
        }

        $functionName = \array_pop($tokens);

        if (!self::tokenIsValidIdentifier($functionName)) {
            return false;
        }

        if (!\function_exists($functionName[1])) {
            return false;
        }

        return true;
    }
}
