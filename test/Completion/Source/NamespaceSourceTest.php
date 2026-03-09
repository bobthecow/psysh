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
use Psy\Completion\Source\NamespaceSource;
use Psy\Test\TestCase;

class NamespaceSourceTest extends TestCase
{
    private NamespaceSource $source;

    protected function setUp(): void
    {
        $this->source = new NamespaceSource();
    }

    public function testAppliesToNamespaceContext()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::NAMESPACE));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::FUNCTION_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CONSTANT));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::UNKNOWN));
    }

    public function testEmptyPrefix()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, '');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('Psy', $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    /**
     * @dataProvider prefixFilteringProvider
     */
    public function testPrefixFiltering(string $prefix, array $expectedNamespaces)
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, $prefix);
        $completions = $this->source->getCompletions($analysis);

        foreach ($expectedNamespaces as $expected) {
            $this->assertContains($expected, $completions, "Expected namespace '{$expected}' not found in completions");
        }
    }

    public function prefixFilteringProvider(): array
    {
        return [
            // Psy namespace
            ['Psy', ['Psy', 'Psy\\Completion', 'Psy\\Completion\\Source']],
            ['Psy\\Completion', ['Psy\\Completion', 'Psy\\Completion\\Source']],

            // Case insensitive
            ['psy', ['Psy', 'Psy\\Completion', 'Psy\\Completion\\Source']],
        ];
    }

    public function testCaseInsensitiveMatching()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, 'psy');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('Psy', $completions);
    }

    public function testIncludesNestedNamespaces()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, 'Psy\\Completion');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('Psy\\Completion', $completions);
        $this->assertContains('Psy\\Completion\\Source', $completions);
    }

    public function testSortingOrder()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, 'Psy\\');
        $completions = $this->source->getCompletions($analysis);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function testNoPrefix()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, '');
        $completions = $this->source->getCompletions($analysis);

        // Should return multiple namespaces
        $this->assertGreaterThan(0, \count($completions));
    }

    public function testExtractsParentNamespaces()
    {
        // For class Psy\Completion\Source\VariableSource, should extract:
        // Psy, Psy\Completion, Psy\Completion\Source
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, 'Psy');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('Psy', $completions);
    }

    public function testNoGlobalNamespace()
    {
        $analysis = new AnalysisResult(CompletionKind::NAMESPACE, '');
        $completions = $this->source->getCompletions($analysis);

        // Should not include classes without namespace (global namespace)
        // like DateTime, Exception, etc.
        foreach ($completions as $completion) {
            $this->assertNotEmpty($completion, 'Empty namespace found in completions');
        }
    }
}
