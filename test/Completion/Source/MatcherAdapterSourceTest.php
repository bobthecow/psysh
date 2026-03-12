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
use Psy\Completion\Source\MatcherAdapterSource;
use Psy\Test\Fixtures\Completion\InfoCapturingMatcher;
use Psy\Test\Fixtures\Completion\MockMatcher;
use Psy\Test\Fixtures\Completion\TokenCapturingMatcher;
use Psy\Test\TestCase;

class MatcherAdapterSourceTest extends TestCase
{
    public function testAppliesToAllKinds()
    {
        $source = new MatcherAdapterSource([]);

        // Legacy matchers could match anything, so adapter applies to all contexts
        $this->assertTrue($source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertTrue($source->appliesToKind(CompletionKind::OBJECT_METHOD));
        $this->assertTrue($source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertTrue($source->appliesToKind(CompletionKind::UNKNOWN));
        $this->assertTrue($source->appliesToKind(CompletionKind::COMMAND));
    }

    public function testGetCompletionsWithNoMatchers()
    {
        $source = new MatcherAdapterSource([]);

        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'foo'
        );

        $completions = $source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithMatchingMatcher()
    {
        $mockMatcher = new MockMatcher(['result1', 'result2', 'result3']);
        $source = new MatcherAdapterSource([$mockMatcher]);

        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'foo'
        );

        $completions = $source->getCompletions($analysis);

        $this->assertCount(3, $completions);
        $this->assertContains('result1', $completions);
        $this->assertContains('result2', $completions);
        $this->assertContains('result3', $completions);
    }

    public function testGetCompletionsWithNonMatchingMatcher()
    {
        $mockMatcher = new MockMatcher([], false); // hasMatched returns false
        $source = new MatcherAdapterSource([$mockMatcher]);

        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'foo'
        );

        $completions = $source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithMultipleMatchers()
    {
        $matcher1 = new MockMatcher(['result1', 'result2']);
        $matcher2 = new MockMatcher(['result3', 'result4']);
        $matcher3 = new MockMatcher([], false); // doesn't match

        $source = new MatcherAdapterSource([$matcher1, $matcher2, $matcher3]);

        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'foo'
        );

        $completions = $source->getCompletions($analysis);

        $this->assertCount(4, $completions);
        $this->assertContains('result1', $completions);
        $this->assertContains('result2', $completions);
        $this->assertContains('result3', $completions);
        $this->assertContains('result4', $completions);
    }

    public function testConvertsObjectMemberToTokens()
    {
        $matcher = new TokenCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_MEMBER,
            'meth',
            '$foo'
        );

        $source->getCompletions($analysis);

        // Verify the matcher received tokens resembling: $foo->meth
        $tokens = $matcher->getLastTokens();
        $this->assertNotEmpty($tokens);

        // Should have variable and string tokens
        $hasVariable = false;
        $hasString = false;
        $hasOperator = false;

        foreach ($tokens as $token) {
            if (\is_array($token)) {
                if (\token_name($token[0]) === 'T_VARIABLE') {
                    $this->assertSame('$foo', $token[1]);
                    $hasVariable = true;
                }
                if (\token_name($token[0]) === 'T_OBJECT_OPERATOR') {
                    $hasOperator = true;
                }
                if (\token_name($token[0]) === 'T_STRING') {
                    $this->assertSame('meth', $token[1]);
                    $hasString = true;
                }
            }
        }

        $this->assertTrue($hasVariable, 'Should have T_VARIABLE token');
        $this->assertTrue($hasOperator, 'Should have T_OBJECT_OPERATOR token');
        $this->assertTrue($hasString, 'Should have T_STRING token');
    }

    public function testConvertsStaticMemberToTokens()
    {
        $matcher = new TokenCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_MEMBER,
            'CONST',
            'MyClass'
        );

        $source->getCompletions($analysis);

        $tokens = $matcher->getLastTokens();
        $this->assertNotEmpty($tokens);

        $hasDoubleColon = false;
        $hasString = false;

        foreach ($tokens as $token) {
            if (\is_array($token)) {
                if (\token_name($token[0]) === 'T_DOUBLE_COLON') {
                    $hasDoubleColon = true;
                }
                if (\token_name($token[0]) === 'T_STRING' && $token[1] === 'CONST') {
                    $hasString = true;
                }
            }
        }

        $this->assertTrue($hasDoubleColon, 'Should have T_DOUBLE_COLON token');
        $this->assertTrue($hasString, 'Should have T_STRING token');
    }

    public function testConvertsVariableToTokens()
    {
        $matcher = new TokenCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'bar'
        );

        $source->getCompletions($analysis);

        $tokens = $matcher->getLastTokens();
        $this->assertNotEmpty($tokens);

        // Should have T_STRING token for 'bar'
        $hasString = false;
        foreach ($tokens as $token) {
            if (\is_array($token) && \token_name($token[0]) === 'T_STRING' && $token[1] === 'bar') {
                $hasString = true;
            }
        }

        $this->assertTrue($hasString, 'Should have T_STRING token for prefix');
    }

    public function testBuildsReadlineInfo()
    {
        $matcher = new InfoCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_MEMBER,
            'format',
            '$date'
        );

        $source->getCompletions($analysis);

        $info = $matcher->getLastInfo();
        $this->assertNotEmpty($info);
        $this->assertArrayHasKey('line_buffer', $info);
        $this->assertArrayHasKey('point', $info);
        $this->assertArrayHasKey('end', $info);

        // Should reconstruct the input
        $this->assertSame('$date->format', $info['line_buffer']);
        $this->assertSame(\strlen('$date->format'), $info['point']);
        $this->assertSame(\strlen('$date->format'), $info['end']);
    }

    public function testUsesOriginalReadlineInfoWhenAvailable()
    {
        $matcher = new InfoCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_MEMBER,
            'format',
            '$date',
            [],
            null,
            [],
            '',
            null,
            [
                'line_buffer' => '$date->format trailing text',
                'point'       => 3,
                'end'         => 13,
                'mark'        => 2,
            ]
        );

        $source->getCompletions($analysis);

        $this->assertSame([
            'line_buffer' => '$date->format trailing text',
            'point'       => 3,
            'end'         => 13,
            'mark'        => 2,
        ], $matcher->getLastInfo());
    }

    public function testFiltersWhitespaceTokens()
    {
        $matcher = new TokenCapturingMatcher();
        $source = new MatcherAdapterSource([$matcher]);

        // Create analysis with tokens that include whitespace
        $analysis = new AnalysisResult(
            CompletionKind::VARIABLE,
            'foo'
        );

        $source->getCompletions($analysis);

        $tokens = $matcher->getLastTokens();

        // Verify no whitespace tokens
        foreach ($tokens as $token) {
            if (\is_array($token)) {
                $this->assertNotSame('T_WHITESPACE', \token_name($token[0]));
            }
        }
    }

    public function testHandlesEmptyPrefix()
    {
        $matcher = new MockMatcher(['completion1', 'completion2']);
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_MEMBER,
            '',  // Empty prefix
            '$obj'
        );

        $completions = $source->getCompletions($analysis);

        $this->assertCount(2, $completions);
    }

    public function testHandlesNullLeftSide()
    {
        $matcher = new MockMatcher(['keyword1', 'keyword2']);
        $source = new MatcherAdapterSource([$matcher]);

        $analysis = new AnalysisResult(
            CompletionKind::KEYWORD,
            'for',
            null  // No left side
        );

        $completions = $source->getCompletions($analysis);

        $this->assertCount(2, $completions);
    }
}
