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
 * A variable name tab completion Matcher.
 *
 * This matcher provides completion for variable names in the current Context.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class VariablesMatcher extends AbstractContextAwareMatcher
{
    /**
     * {@inheritdoc}
     */
    protected function getInput(array $tokens, array $t_valid = null)
    {
        return parent::getInput($tokens, [self::T_VARIABLE, '$', '']);
    }

    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = [])
    {
        $input = $this->getInput($tokens);
        if ($input === false) {
            return [];
        }

        // '$' is a readline completion word-break character (refer to
        // AutoCompleter::WORD_BREAK_CHARS), and so the completion
        // candidates we generate must not include the leading '$' --
        // *unless* we are completing an empty string, in which case
        // the '$' is required.
        if ($input === '') {
            $dollarPrefix = true;
        }
        else {
            $dollarPrefix = false;
            $input = \str_replace('$', '', $input);
        }

        return \array_filter(
            \array_keys($this->getVariables($dollarPrefix)),
            function ($variable) use ($input) {
                return AbstractMatcher::startsWith($input, $variable);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token = \array_pop($tokens);

        switch (true) {
            case self::tokenIs($token, self::T_VARIABLE):
            case in_array($token, ['', '$'], true):
                return true;
        }

        return false;
    }
}
