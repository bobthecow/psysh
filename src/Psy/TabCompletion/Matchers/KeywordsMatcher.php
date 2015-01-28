<?php

namespace Psy\TabCompletion\Matchers;

use Psy\TabCompletion\Rulers\ClassNamesRuler;

/**
 * Class KeywordsMatcher
 * @package Psy\TabCompletion\Matchers
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
     * {@inheritDoc}
     */
    protected function buildRules()
    {
        $this->rules[] = new ClassNamesRuler();
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
}
