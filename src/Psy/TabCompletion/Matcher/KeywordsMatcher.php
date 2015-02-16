<?php

namespace Psy\TabCompletion\Matcher;

/**
 * Class KeywordsMatcher
 * @package Psy\TabCompletion\Matcher
 */
class KeywordsMatcher extends AbstractMatcher
{
    protected $keywords = array(
        'array', 'clone', 'declare', 'die', 'echo', 'empty', 'eval', 'exit', 'include',
        'include_once', 'isset', 'list', 'print',  'require', 'require_once', 'unset',
    );

    protected $mandatoryStartKeywords = array(
        'die', 'echo', 'print', 'unset',
    );

    /**
     * @return array
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param $keyword
     * @return bool
     */
    public function isKeyword($keyword)
    {
        return in_array($keyword, $this->keywords);
    }

    /**
     * {@inheritDoc}
     */
    public function getMatches(array $tokens, array $info = array())
    {
        $input = $this->getInput($tokens);

        return array_filter($this->keywords, function ($keyword) use ($input) {
            return AbstractMatcher::startsWith($input, $keyword);
        });
    }

    /**
     * @param  array $tokens
     * @return bool
     */
    public function hasMatched(array $tokens)
    {
        $token = array_pop($tokens);
        $prevToken = array_pop($tokens);

        switch (true) {
            case self::hasToken(array(self::T_OPEN_TAG, self::T_VARIABLE), $token):
            case is_string($token) && $token === '$':
            case self::isOperator($token):
                return true;
        }

        return false;
    }
}
