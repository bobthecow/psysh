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
use Psy\Completion\Source\VariableSource;
use Psy\Context;
use Psy\Test\TestCase;

class VariableSourceTest extends TestCase
{
    private Context $context;
    private VariableSource $source;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->source = new VariableSource($this->context);
    }

    public function testAppliesToVariableContext()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::VARIABLE));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::FUNCTION_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::UNKNOWN));
    }

    public function testEmptyPrefix()
    {
        $this->context->setAll([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ]);

        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('_', $completions);
        $this->assertContains('bar', $completions);
        $this->assertContains('baz', $completions);
        $this->assertContains('foo', $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function testReturnsAllVariablesRegardlessOfPrefix()
    {
        // Sources return ALL candidates; fuzzy matching happens in CompletionEngine
        $vars = [
            'foo'     => 1,
            'fooBar'  => 2,
            'foo_baz' => 3,
            'bar'     => 4,
            'baz'     => 5,
            'test'    => 6,
            'testing' => 7,
        ];

        $this->context->setAll($vars);

        $allVariables = ['_', 'bar', 'baz', 'foo', 'fooBar', 'foo_baz', 'test', 'testing'];

        // All prefixes should return the same set of all variables
        $testPrefixes = ['', 'f', 'b', 't', 'fo', 'foo', 'fooB', 'foo_', 'F', 'bar', 'testing', 'xyz', 'zzz'];

        foreach ($testPrefixes as $prefix) {
            $analysis = new AnalysisResult(CompletionKind::VARIABLE, $prefix);
            $completions = $this->source->getCompletions($analysis);

            $this->assertEquals($allVariables, $completions, "Failed for prefix: {$prefix}");
        }
    }

    public function testMagicVariables()
    {
        // Set some return value and exception
        $this->context->setReturnValue('test');
        $this->context->setLastException(new \Exception());
        $this->context->setLastStdout('output');

        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('_', $completions);     // Last return value
        $this->assertContains('_e', $completions);    // Last exception
        $this->assertContains('__out', $completions); // Last stdout
    }

    public function testBoundObjectThis()
    {
        $this->context->setBoundObject(new \DateTime());

        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('this', $completions);
    }

    public function testNoBoundObjectThis()
    {
        // No bound object
        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        // Should NOT include 'this'
        $this->assertNotContains('this', $completions);
    }

    public function testEmptyContext()
    {
        // No variables set
        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        // Should only have magic variable '_'
        $this->assertEquals(['_'], $completions);
    }

    public function testSortingOrder()
    {
        $this->context->setAll([
            'zzz' => 1,
            'aaa' => 2,
            'mmm' => 3,
            'bbb' => 4,
        ]);

        $analysis = new AnalysisResult(CompletionKind::VARIABLE, '');
        $completions = $this->source->getCompletions($analysis);

        // Find our test variables in the results (skip magic variables)
        $testVars = \array_filter($completions, fn ($v) => \in_array($v, ['aaa', 'bbb', 'mmm', 'zzz']));
        $testVars = \array_values($testVars);

        $this->assertEquals(['aaa', 'bbb', 'mmm', 'zzz'], $testVars);
    }
}
