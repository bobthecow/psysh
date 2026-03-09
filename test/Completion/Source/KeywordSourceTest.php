<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Source;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\KeywordSource;
use Psy\Test\TestCase;

class KeywordSourceTest extends TestCase
{
    private KeywordSource $source;

    protected function setUp(): void
    {
        $this->source = new KeywordSource();
    }

    public function testAppliesToKeywordContext()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::KEYWORD));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::FUNCTION_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::COMMAND));
    }

    public function testEmptyPrefix()
    {
        $analysis = new AnalysisResult(CompletionKind::KEYWORD, '');
        $completions = $this->source->getCompletions($analysis);

        $this->assertEquals($this->getExpectedKeywords(), $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function testAlwaysReturnsAllKeywords()
    {
        $expectedKeywords = $this->getExpectedKeywords();

        $testPrefixes = ['', 'e', 'ec', 'xyz', 'foo', 'isset'];

        foreach ($testPrefixes as $prefix) {
            $analysis = new AnalysisResult(CompletionKind::KEYWORD, $prefix);
            $completions = $this->source->getCompletions($analysis);
            $this->assertEquals($expectedKeywords, $completions, "Failed for prefix: {$prefix}");
        }
    }

    public function testSortingOrder()
    {
        $analysis = new AnalysisResult(CompletionKind::KEYWORD, '');
        $completions = $this->source->getCompletions($analysis);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function testAllExpectedKeywords()
    {
        $analysis = new AnalysisResult(CompletionKind::KEYWORD, '');
        $completions = $this->source->getCompletions($analysis);

        $expectedKeywords = $this->getExpectedKeywords();

        foreach ($expectedKeywords as $keyword) {
            $this->assertContains($keyword, $completions, "Missing keyword: {$keyword}");
        }

        $this->assertCount(\count($expectedKeywords), $completions);
    }

    /**
     * @return string[]
     */
    private function getExpectedKeywords(): array
    {
        $keywords = [
            'array', 'clone', 'declare', 'die', 'echo', 'empty', 'eval', 'exit',
            'fn', 'include', 'include_once', 'isset', 'list', 'print', 'require',
            'require_once', 'unset', 'yield',
        ];

        if (\PHP_VERSION_ID >= 80000) {
            $keywords[] = 'match';
            \sort($keywords);
        }

        return $keywords;
    }
}
