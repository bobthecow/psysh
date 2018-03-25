<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion\Matcher;

/**
 * A PHP keyword tab completion Matcher.
 *
 * This matcher provides completion for all function-like PHP keywords.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class KeywordsMatcher extends AbstractMatcher
{
    protected $keywords = [
        'array', 'clone', 'declare', 'die', 'echo', 'empty', 'eval', 'exit', 'include',
        'include_once', 'isset', 'list', 'print',  'require', 'require_once', 'unset',
    ];

    protected $mandatoryStartKeywords = [
        'die', 'echo', 'print', 'unset',
    ];

    /**
     * Get all (completable) PHP keywords.
     *
     * @return array
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Check whether $keyword is a (completable) PHP keyword.
     *
     * @param string $keyword
     *
     * @return bool
     */
    public function isKeyword($keyword)
    {
        return in_array($keyword, $this->keywords);
    }

    /**
     * {@inheritdoc}
     */
    public function getMatches(array $tokens, array $info = [])
    {
        $input = $this->getInput($tokens);

        return array_filter($this->keywords, function ($keyword) use ($input) {
            return AbstractMatcher::startsWith($input, $keyword);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function hasMatched(array $tokens)
    {
        $token     = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::hasToken([self::T_OPEN_TAG, self::T_VARIABLE], $token):
//            case is_string($token) && $token === '$':
            case self::hasToken([self::T_OPEN_TAG, self::T_VARIABLE], $prevToken) &&
                self::tokenIs($token, self::T_STRING):
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
