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
use Psy\Completion\Source\PropertySource;
use Psy\Test\Fixtures\Completion\TestChildClassForPropertySource;
use Psy\Test\Fixtures\Completion\TestClassForPropertySource;
use Psy\Test\TestCase;

class PropertySourceTest extends TestCase
{
    private PropertySource $source;

    protected function setUp(): void
    {
        $this->source = new PropertySource();
    }

    public function testAppliesToObjectPropertyKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
    }

    public function testAppliesToObjectMemberUnionKind()
    {
        // OBJECT_MEMBER is a union that includes OBJECT_PROPERTY
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
    }

    public function testDoesNotApplyToOtherKinds()
    {
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_PROPERTY));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testReturnsEmptyWhenNoType()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, 'prop');

        $completions = $this->source->getCompletions($analysis);

        $this->assertSame([], $completions);
    }

    public function testReturnsEmptyWhenTypeDoesNotExist()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, 'prop', null, 'NonExistentClass');

        $completions = $this->source->getCompletions($analysis);

        $this->assertSame([], $completions);
    }

    public function testReturnsPropertiesWithoutPrefix()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, '', null, TestClassForPropertySource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);
        $this->assertContains('anotherPublicProperty', $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertSame($sorted, $completions);
    }

    public function testReturnsAllPropertiesRegardlessOfPrefix()
    {
        // Sources return all candidates; fuzzy filtering happens in CompletionEngine
        $analysis1 = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, 'pub', null, TestClassForPropertySource::class);
        $analysis2 = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, 'xyz', null, TestClassForPropertySource::class);

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);

        $this->assertContains('publicProperty', $completions1);
        $this->assertContains('anotherPublicProperty', $completions1);
    }

    public function testPrefixMatchingIsCaseInsensitive()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, 'PUB', null, TestClassForPropertySource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);
    }

    public function testOnlyIncludesPublicProperties()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, '', null, TestClassForPropertySource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);

        $this->assertNotContains('protectedProperty', $completions);
        $this->assertNotContains('privateProperty', $completions);
    }

    public function testIncludesBothStaticAndInstanceProperties()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, '', null, TestClassForPropertySource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);
        $this->assertContains('publicStaticProperty', $completions);
    }

    public function testIncludesInheritedProperties()
    {
        $analysis = new AnalysisResult(CompletionKind::OBJECT_PROPERTY, '', null, TestChildClassForPropertySource::class);

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);

        $this->assertContains('childProperty', $completions);
    }
}
