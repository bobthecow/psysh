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
use Psy\Completion\Source\StaticPropertySource;
use Psy\Test\TestCase;

class StaticPropertySourceTest extends TestCase
{
    private StaticPropertySource $source;

    protected function setUp(): void
    {
        $this->source = new StaticPropertySource();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_PROPERTY));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testGetCompletionsWithCustomClass()
    {
        $className = \get_class(new class() {
            public static $publicStatic = 'value';
            protected static $protectedStatic = 'value';
            private static $privateStatic = 'value';
            public $instanceProp = 'value';
        });

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            '',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicStatic', $completions);

        $this->assertNotContains('instanceProp', $completions);

        $this->assertNotContains('protectedStatic', $completions);
        $this->assertNotContains('privateStatic', $completions);
    }

    public function testReturnsAllStaticPropertiesRegardlessOfPrefix()
    {
        $className = \get_class(new class() {
            public static $staticFoo = 'value';
            public static $staticBar = 'value';
            public static $otherProp = 'value';
        });

        $analysis1 = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            'static',
            $className,
            $className
        );

        $analysis2 = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            'xyz',
            $className,
            $className
        );

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);

        $this->assertContains('staticFoo', $completions1);
        $this->assertContains('staticBar', $completions1);
        $this->assertContains('otherProp', $completions1);
    }

    public function testGetCompletionsCaseInsensitivePrefix()
    {
        $className = \get_class(new class() {
            public static $staticFoo = 'value';
        });

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            'STATIC',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('staticFoo', $completions);
    }

    public function testGetCompletionsWithNoLeftSide()
    {
        $analysis = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
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
            CompletionKind::STATIC_PROPERTY,
            '',
            'NonExistentClass',
            'NonExistentClass'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithInheritedStaticProperties()
    {
        // Create parent class with static property
        $parentClassName = \get_class(new class() {
            public static $inheritedProp = 'value';
        });

        // Create child class
        $childClassName = \get_class(new class() extends \DateTime {
            public static $customProp = 'value';
        });

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            '',
            $childClassName,
            $childClassName
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('customProp', $completions);
    }

    public function testGetCompletionsEmptyForClassWithoutStaticProperties()
    {
        $className = \get_class(new class() {
            public $instanceProp = 'value';

            public function method()
            {
            }
        });

        $analysis = new AnalysisResult(
            CompletionKind::STATIC_PROPERTY,
            '',
            $className,
            $className
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }
}
