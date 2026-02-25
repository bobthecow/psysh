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
use Psy\Completion\Source\MagicPropertySource;
use Psy\Test\Fixtures\Util\MagicChild;
use Psy\Test\Fixtures\Util\MagicClass;
use Psy\Test\TestCase;
use Psy\Util\Docblock;

class MagicPropertySourceTest extends TestCase
{
    private MagicPropertySource $source;

    protected function setUp(): void
    {
        $this->source = new MagicPropertySource();
        Docblock::clearMagicCache();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_PROPERTY));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
    }

    public function testGetCompletionsFromClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            MagicClass::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('title', $completions);
        $this->assertContains('id', $completions);
        $this->assertContains('password', $completions);
    }

    public function testGetCompletionsFromObjectInstance()
    {
        $obj = new MagicClass();

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            [],
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('title', $completions);
        $this->assertContains('id', $completions);
        $this->assertContains('password', $completions);
    }

    public function testInheritedMagicProperties()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            MagicChild::class
        );

        $completions = $this->source->getCompletions($analysis);

        // Own properties
        $this->assertContains('childProperty', $completions);

        // Parent properties
        $this->assertContains('parentProperty', $completions);

        // Interface properties
        $this->assertContains('interfaceProperty', $completions);

        // Trait properties
        $this->assertContains('traitProperty', $completions);
    }

    public function testReturnsEmptyForNoTypes()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
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
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            'NonExistentClass'
        );

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testDeduplicatesResults()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            MagicClass::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertCount(\count(\array_unique($completions)), $completions);
    }
}
