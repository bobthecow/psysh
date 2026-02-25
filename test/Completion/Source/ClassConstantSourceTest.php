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
use Psy\Completion\Source\ClassConstantSource;
use Psy\Test\TestCase;

class ClassConstantSourceTest extends TestCase
{
    private ClassConstantSource $source;

    protected function setUp(): void
    {
        $this->source = new ClassConstantSource();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::CLASS_CONSTANT));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_METHOD));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testGetCompletionsFromDateTime()
    {
        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            'DateTime',
            'DateTime'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('ATOM', $completions);
        $this->assertContains('RFC3339', $completions);
        $this->assertContains('W3C', $completions);
    }

    public function testReturnsAllConstantsRegardlessOfPrefix()
    {
        // Sources return all candidates; fuzzy filtering happens in CompletionEngine
        $analysis1 = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            'RFC',
            'DateTime',
            'DateTime'
        );

        $analysis2 = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            'xyz',
            'DateTime',
            'DateTime'
        );

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);
        $this->assertContains('ATOM', $completions1);
        $this->assertContains('RFC3339', $completions1);
        $this->assertContains('RFC3339_EXTENDED', $completions1);
        $this->assertContains('W3C', $completions1);
    }

    public function testGetCompletionsWithCustomClass()
    {
        $className = \get_class(new class() {
            public const PUBLIC_CONST = 'value';
            protected const PROTECTED_CONST = 'value';
            private const PRIVATE_CONST = 'value';
        });

        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('PUBLIC_CONST', $completions);
        $this->assertNotContains('PROTECTED_CONST', $completions);
        $this->assertNotContains('PRIVATE_CONST', $completions);
    }

    public function testGetCompletionsWithNoLeftSide()
    {
        $analysis = new AnalysisResult(CompletionKind::CLASS_CONSTANT, '');

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testGetCompletionsWithInvalidClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            'NonExistentClass',
            'NonExistentClass'
        );

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testGetCompletionsWithInheritedConstants()
    {
        $className = \get_class(new class() extends \DateTime {
            public const CUSTOM_CONST = 'value';
        });

        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('CUSTOM_CONST', $completions);
        $this->assertContains('ATOM', $completions);
        $this->assertContains('RFC3339', $completions);
    }

    public function testGetCompletionsEmptyForClassWithoutConstants()
    {
        $className = \get_class(new class() {
            public static $staticProp = 'value';

            public function method()
            {
            }
        });

        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            $className,
            $className
        );

        $this->assertEmpty($this->source->getCompletions($analysis));
    }

    public function testGetCompletionsWithInterface()
    {
        $analysis = new AnalysisResult(
            CompletionKind::CLASS_CONSTANT,
            '',
            'DateTimeInterface',
            'DateTimeInterface'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('ATOM', $completions);
        $this->assertContains('RFC3339', $completions);
    }
}
