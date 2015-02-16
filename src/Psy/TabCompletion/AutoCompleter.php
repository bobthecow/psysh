<?php

namespace Psy\TabCompletion;

use Psy\TabCompletion\Matcher\AbstractMatcher;

/**
 * Class AutoCompleter
 * @package Psy\TabCompletion
 */
class AutoCompleter
{
    /** @var Matcher\AbstractMatcher[]  */
    protected $matchers;

    public function addMatcher(AbstractMatcher $matcher)
    {
        $this->matchers[] = $matcher;
    }

    public function activate()
    {
        readline_completion_function(array(&$this, 'callback'));
    }

    /**
     * @param string $input Readline current word
     * @param int    $index Current word index
     * @param array  $info  readline_info() data
     *
     * @return array
     */
    public function processCallback($input, $index, $info = array())
    {
        $line = substr($info['line_buffer'], 0, $info['end']);
        $tokens = token_get_all('<?php ' . $line);
        // remove whitespaces
        $tokens = array_filter($tokens, function ($token) {
            return !AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE);
        });

        $matches = array();
        foreach ($this->matchers as $matcher) {
            if ($matcher->hasMatched($tokens)) {
                $matches = array_merge($matcher->getMatches($tokens), $matches);
            }
        }

        $matches = array_unique($matches);

        return !empty($matches) ? $matches : array('');
    }

    /**
     * @param $input
     * @param $index
     * @return array
     */
    public function callback($input, $index)
    {
        return $this->processCallback($input, $index, readline_info());
    }

    public function __destruct()
    {
        readline_callback_handler_remove();
    }
}
