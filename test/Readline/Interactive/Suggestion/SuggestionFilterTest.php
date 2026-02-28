<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Suggestion;

use Psy\Readline\Interactive\Suggestion\SuggestionFilter;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Test\TestCase;

class SuggestionFilterTest extends TestCase
{
    private SuggestionFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new SuggestionFilter();
    }

    public function testValidSuggestion()
    {
        $result = $this->filter->isValid('$na', 'me = "Alice"');

        $this->assertTrue($result);
    }

    public function testWhitespaceOnlySuggestionInvalid()
    {
        $result = $this->filter->isValid('$name', '   ');

        $this->assertFalse($result);
    }

    public function testFunctionAfterObjectOperatorInvalid()
    {
        // Shouldn't suggest function calls after ->
        $result = $this->filter->isValid('$obj->', 'someMethod()');

        $this->assertFalse($result);
    }

    public function testPropertyAfterObjectOperatorValid()
    {
        // Should suggest properties/methods without parens after ->
        $result = $this->filter->isValid('$obj->', 'someProperty');

        $this->assertTrue($result);
    }

    public function testScoreHistoryHigherThanCompletion()
    {
        $histSuggestion = new SuggestionResult('me = "Alice"', 'history', '$name = "Alice"');
        $compSuggestion = new SuggestionResult('me = "Alice"', 'completion', '$name = "Alice"');

        $histScore = $this->filter->score('$na', $histSuggestion);
        $compScore = $this->filter->score('$na', $compSuggestion);

        $this->assertGreaterThan($compScore, $histScore);
    }

    public function testScoreShorterSuggestionHigher()
    {
        $shortSuggestion = new SuggestionResult('ap', 'history', 'array_map');
        $longSuggestion = new SuggestionResult(
            ' very long suggestion that goes on and on and on',
            'history',
            'array_map very long suggestion that goes on and on and on'
        );

        $shortScore = $this->filter->score('array_m', $shortSuggestion);
        $longScore = $this->filter->score('array_m', $longSuggestion);

        $this->assertGreaterThan($longScore, $shortScore);
    }

    public function testScorePrefixMatchHigher()
    {
        $prefixMatch = new SuggestionResult('me = "Alice"', 'history', '$name = "Alice"');
        $noMatch = new SuggestionResult('array_pop', 'history', 'array_pop');

        $prefixScore = $this->filter->score('$na', $prefixMatch);
        $noMatchScore = $this->filter->score('$na', $noMatch);

        $this->assertGreaterThan($noMatchScore, $prefixScore);
    }

    public function testScoreSingleLineHigher()
    {
        $singleLine = new SuggestionResult('me = "Alice"', 'history', '$name = "Alice"');
        $multiLine = new SuggestionResult("me = \"Alice\"\necho \$name", 'history', "\$name = \"Alice\"\necho \$name");

        $singleScore = $this->filter->score('$na', $singleLine);
        $multiScore = $this->filter->score('$na', $multiLine);

        $this->assertGreaterThan($multiScore, $singleScore);
    }

    public function testScoreRangeBounded()
    {
        // Score should always be between 0 and 100
        $suggestion = new SuggestionResult('test', 'history', 'test');

        $score = $this->filter->score('', $suggestion);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
}
