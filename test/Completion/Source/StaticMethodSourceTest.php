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
use Psy\Completion\Source\StaticMethodSource;
use Psy\Test\Fixtures\ClassWithStaticMagicMethods;
use Psy\Test\TestCase;

class StaticMethodSourceTest extends TestCase
{
    private StaticMethodSource $source;

    protected function setUp(): void
    {
        $this->source = new StaticMethodSource();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_METHOD));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testGetCompletionsFromClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            'DateTime',
            'DateTime'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('createFromFormat', $completions);
        $this->assertContains('getLastErrors', $completions);

        $this->assertNotContains('__construct', $completions);
        $this->assertNotContains('__toString', $completions);

        $this->assertNotContains('format', $completions);
        $this->assertNotContains('modify', $completions);
    }

    public function testReturnsAllStaticMethodsRegardlessOfPrefix()
    {
        $analysis1 = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            'create',
            'DateTime',
            'DateTime'
        );

        $analysis2 = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            'xyz',
            'DateTime',
            'DateTime'
        );

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);

        $this->assertContains('createFromFormat', $completions1);
        $this->assertContains('createFromImmutable', $completions1);
        $this->assertContains('getLastErrors', $completions1);
    }

    public function testGetCompletionsCaseInsensitivePrefix()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            'CREATE',
            'DateTime',
            'DateTime'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('createFromFormat', $completions);
    }

    public function testGetCompletionsWithCustomClass()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            ClassWithStaticMagicMethods::class,
            ClassWithStaticMagicMethods::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicStaticMethod', $completions);

        $this->assertNotContains('__callStatic', $completions);

        $this->assertNotContains('instanceMethod', $completions);

        $this->assertNotContains('protectedStaticMethod', $completions);
        $this->assertNotContains('privateStaticMethod', $completions);
    }

    public function testMagicMethodsShownWithDoubleUnderscorePrefix()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '__',
            ClassWithStaticMagicMethods::class,
            ClassWithStaticMagicMethods::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicStaticMethod', $completions);
        $this->assertContains('__callStatic', $completions);
    }

    public function testGetCompletionsWithNoLeftSide()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            null,
            null
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithInvalidClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            'NonExistentClass',
            'NonExistentClass'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithInheritedStaticMethods()
    {
        $className = \get_class(new class() extends \DateTime {
            public static function customStaticMethod()
            {
            }
        });

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_METHOD,
            '',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('customStaticMethod', $completions);

        $this->assertContains('createFromFormat', $completions);
    }
}
