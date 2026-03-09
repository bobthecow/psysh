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
use Psy\Completion\Source\MethodSource;
use Psy\Test\Fixtures\Completion\TestClassForMethodSource;
use Psy\Test\TestCase;

class MethodSourceTest extends TestCase
{
    private MethodSource $source;

    protected function setUp(): void
    {
        $this->source = new MethodSource();
    }

    public function testAppliesToObjectMethodKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
    }

    public function testAppliesToObjectMemberUnionKind()
    {
        // OBJECT_MEMBER is a union that includes OBJECT_METHOD
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
    }

    public function testDoesNotApplyToOtherKinds()
    {
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_METHOD));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testReturnsEmptyWhenNoType()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, 'for');

        $completions = $this->source->getCompletions($analysis);

        $this->assertSame([], $completions);
    }

    public function testReturnsEmptyWhenTypeDoesNotExist()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, 'for', null, 'NonExistentClass');

        $completions = $this->source->getCompletions($analysis);

        $this->assertSame([], $completions);
    }

    public function testReturnsDateTimeMethodsWithoutPrefix()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, '', null, 'DateTime');

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('format', $completions);
        $this->assertContains('modify', $completions);
        $this->assertContains('getTimestamp', $completions);
        $this->assertContains('setTimezone', $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertSame($sorted, $completions);
    }

    public function testReturnsAllMethodsRegardlessOfPrefix()
    {
        // Sources return all candidates; fuzzy filtering happens in CompletionEngine
        $analysis1 = new AnalysisResult(CompletionKind::OBJECT_METHOD, 'get', null, 'DateTime');
        $analysis2 = new AnalysisResult(CompletionKind::OBJECT_METHOD, 'xyz', null, 'DateTime');

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);

        $this->assertContains('getTimestamp', $completions1);
        $this->assertContains('getTimezone', $completions1);
        $this->assertContains('getOffset', $completions1);
        $this->assertContains('format', $completions1);
        $this->assertContains('modify', $completions1);
    }

    public function testPrefixMatchingIsCaseInsensitive()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, 'GET', null, 'DateTime');

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('getTimestamp', $completions);
        $this->assertContains('getTimezone', $completions);
    }

    public function testIncludesInheritedMethods()
    {
        // RuntimeException extends Exception but doesn't override getMessage, getCode, etc.
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, '', null, 'RuntimeException');

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('getMessage', $completions);
        $this->assertContains('getCode', $completions);
        $this->assertContains('getFile', $completions);
        $this->assertContains('getLine', $completions);
    }

    public function testOnlyIncludesPublicMethods()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, '', null, TestClassForMethodSource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicMethod', $completions);

        $this->assertNotContains('protectedMethod', $completions);
        $this->assertNotContains('privateMethod', $completions);
    }

    public function testIncludesBothStaticAndInstanceMethods()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_METHOD, '', null, TestClassForMethodSource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicMethod', $completions);
        $this->assertContains('publicStaticMethod', $completions);
    }
}
