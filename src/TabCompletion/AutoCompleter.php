<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2022 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\TabCompletion;

use Psy\Readline\Readline;
use Psy\TabCompletion\Matcher\AbstractMatcher;

/**
 * A readline tab completion service.
 *
 * @author Marc Garcia <markcial@gmail.com>
 */
class AutoCompleter
{
    /** @var Matcher\AbstractMatcher[] */
    protected $matchers;

    /** @var ?Readline */
    protected $readline;

    /**
     * Register a tab completion Matcher.
     *
     * @param AbstractMatcher $matcher
     */
    public function addMatcher(AbstractMatcher $matcher)
    {
        $this->matchers[] = $matcher;
    }

    /**
     * Handle readline completion.
     *
     * @param string $input Readline current word
     * @param int    $index Current word index
     * @phpstan-param array{line_buffer: string, end: int} $info {@see readline_info()} data
     *
     * @return list<string>
     */
    public function complete(string $input, int $index, array $info = []): array
    {
        // Some (Windows?) systems provide incomplete `readline_info`, so let's
        // try to work around it.
        $line = $info['line_buffer'];
        if (isset($info['end'])) {
            $line = \substr($line, 0, $info['end']);
        }
        if ($line === '' && $input !== '') {
            $line = $input;
        }

        $tokens = \token_get_all('<?php '.$line);

        // remove whitespaces
        $tokens = \array_filter($tokens, function ($token) {
            return !AbstractMatcher::tokenIs($token, AbstractMatcher::T_WHITESPACE);
        });
        // reset index from 0 to remove missing index number
        $tokens = \array_values($tokens);

        $matches = [];
        foreach ($this->matchers as $matcher) {
            if ($matcher->hasMatched($tokens)) {
                $matches = \array_merge($matcher->getMatches($tokens), $matches);
            }
        }

        return \array_values(\array_unique($matches)) ?: [''];
    }

    /**
     * The readline_completion_function callback handler.
     *
     * @see AutoCompleter::complete()
     *
     * @param string $input
     * @param int    $index
     *
     * @return array
     */
    public function callback(string $input, int $index): array
    {
        return $this->complete($input, $index, \readline_info());
    }

    /**
     * Remove readline callback handler on destruct.
     */
    public function __destruct()
    {
        if (isset($this->readline)) {
            $this->readline->deactivateCompletion();
            $this->readline = null;
        }
    }
}
