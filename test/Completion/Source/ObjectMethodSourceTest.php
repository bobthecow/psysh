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
use Psy\Completion\Source\ObjectMethodSource;
use Psy\Test\Fixtures\ObjectWithMagicMethods;
use Psy\Test\TestCase;

class ObjectMethodSourceTest extends TestCase
{
    private ObjectMethodSource $source;

    protected function setUp(): void
    {
        $this->source = new ObjectMethodSource();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_METHOD));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_METHOD));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testGetCompletionsFromObjectInstance()
    {
        $date = new \DateTime('2025-01-01');

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$date',
            'DateTime',
            $date
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('format', $completions);
        $this->assertContains('modify', $completions);
        $this->assertContains('setTimezone', $completions);

        $this->assertNotContains('__construct', $completions);
        $this->assertNotContains('__toString', $completions);
        $this->assertNotContains('__wakeup', $completions);
    }

    public function testGetCompletionsFromClassName()
    {
        // No object instance, only class name
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$date',
            'DateTime'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('format', $completions);
        $this->assertContains('modify', $completions);
        $this->assertContains('setTimezone', $completions);
    }

    public function testReturnsAllMethodsRegardlessOfPrefix()
    {
        $date = new \DateTime('2025-01-01');

        $analysis1 = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            'set',
            '$date',
            'DateTime',
            $date
        );

        $analysis2 = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            'xyz',
            '$date',
            'DateTime',
            $date
        );

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);
        $this->assertContains('setTimezone', $completions1);
        $this->assertContains('format', $completions1);
        $this->assertContains('modify', $completions1);
    }

    public function testGetCompletionsCaseInsensitivePrefix()
    {
        $date = new \DateTime('2025-01-01');

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            'SET',
            '$date',
            'DateTime',
            $date
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('setTimezone', $completions);
        $this->assertContains('setDate', $completions);
    }

    public function testGetCompletionsWithCustomObject()
    {
        $obj = new ObjectWithMagicMethods();

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            null,
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicMethod', $completions);

        $this->assertNotContains('__construct', $completions);
        $this->assertNotContains('__toString', $completions);
        $this->assertNotContains('__invoke', $completions);

        $this->assertNotContains('protectedMethod', $completions);
        $this->assertNotContains('privateMethod', $completions);
    }

    public function testMagicMethodsShownWithDoubleUnderscorePrefix()
    {
        $obj = new ObjectWithMagicMethods();

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '__',
            '$obj',
            null,
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicMethod', $completions);
        $this->assertContains('__construct', $completions);
        $this->assertContains('__toString', $completions);
        $this->assertContains('__invoke', $completions);
    }

    public function testMagicMethodsShownWithDoubleUnderscorePrefixViaReflection()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '__',
            '$obj',
            ObjectWithMagicMethods::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('__construct', $completions);
        $this->assertContains('__toString', $completions);
        $this->assertContains('__invoke', $completions);
        $this->assertContains('publicMethod', $completions);
    }

    public function testGetCompletionsWithNoLeftSide()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            null,
            null
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithNonObjectValue()
    {
        // leftSideValue is not an object
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$str',
            'string',
            'not an object'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithInvalidClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            'NonExistentClass'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testRuntimeReflectionPreferredOverStatic()
    {
        // Create an object with a dynamic method
        $obj = new class() extends \DateTime {
            // This subclass might have different methods
            public function customMethod()
            {
            }
        };

        // Provide both runtime value and type
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_METHOD,
            '',
            '$obj',
            'DateTime',  // Parent class type
            $obj         // Actual instance
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('customMethod', $completions);
    }
}
