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
use Psy\Completion\Source\MagicMethodSource;
use Psy\Test\Fixtures\Util\MagicChild;
use Psy\Test\Fixtures\Util\MagicClass;
use Psy\Test\TestCase;
use Psy\Util\Docblock;

class MagicMethodSourceTest extends TestCase
{
    private MagicMethodSource $source;

    protected function setUp(): void
    {
        $this->source = new MagicMethodSource();
        Docblock::clearMagicCache();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_METHOD));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
    }

    public function testGetCompletionsFromClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            MagicClass::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('getName', $completions);
        $this->assertContains('setName', $completions);
        $this->assertContains('find', $completions);
        $this->assertContains('where', $completions);
    }

    public function testGetCompletionsFromObjectInstance()
    {
        $obj = new MagicClass();

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            [],
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('getName', $completions);
        $this->assertContains('setName', $completions);
    }

    public function testStaticContextFiltersNonStaticMethods()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            'MagicClass',
            MagicClass::class
        );

        $completions = $this->source->getCompletions($analysis);

        // find() is declared as static
        $this->assertContains('find', $completions);

        // These are not static
        $this->assertNotContains('getName', $completions);
        $this->assertNotContains('setName', $completions);
        $this->assertNotContains('where', $completions);
    }

    public function testInheritedMagicMethods()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            MagicChild::class
        );

        $completions = $this->source->getCompletions($analysis);

        // Own methods
        $this->assertContains('getChildMethod', $completions);
        $this->assertContains('overriddenMethod', $completions);

        // Parent methods
        $this->assertContains('getParentMethod', $completions);

        // Interface methods
        $this->assertContains('getInterfaceMethod', $completions);

        // Trait methods
        $this->assertContains('getTraitMethod', $completions);
    }

    public function testReturnsEmptyForNoTypes()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            null,
            null,
            null
        );

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testReturnsEmptyForInvalidClass()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            'NonExistentClass'
        );

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testDeduplicatesResults()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            MagicClass::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertCount(\count(\array_unique($completions)), $completions);
    }
}
