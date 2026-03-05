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
use Psy\Completion\Source\ObjectPropertySource;
use Psy\Test\Fixtures\Completion\TestClassForPropertySource;
use Psy\Test\TestCase;

class ObjectPropertySourceTest extends TestCase
{
    private ObjectPropertySource $source;

    protected function setUp(): void
    {
        $this->source = new ObjectPropertySource();
    }

    public function testAppliesToKind()
    {
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_PROPERTY));
        $this->assertTrue($this->source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::STATIC_PROPERTY));
        $this->assertFalse($this->source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testGetCompletionsFromObjectInstance()
    {
        $obj = new class() {
            public $publicProp = 'value1';
            public $anotherProp = 'value2';
            protected $protectedProp = 'value3';
            private $privateProp = 'value4';
        };

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            null,
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProp', $completions);
        $this->assertContains('anotherProp', $completions);

        $this->assertNotContains('protectedProp', $completions);
        $this->assertNotContains('privateProp', $completions);
    }

    public function testGetCompletionsWithDynamicProperties()
    {
        $obj = new \stdClass();
        $obj->dynamicProp1 = 'value1';
        $obj->dynamicProp2 = 'value2';
        $obj->foo = 'bar';

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            'stdClass',
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('dynamicProp1', $completions);
        $this->assertContains('dynamicProp2', $completions);
        $this->assertContains('foo', $completions);
    }

    public function testGetCompletionsFromClassName()
    {
        // No object instance, only class name — uses static reflection
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            TestClassForPropertySource::class
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('publicProperty', $completions);
        $this->assertContains('anotherPublicProperty', $completions);
        $this->assertNotContains('protectedProperty', $completions);
        $this->assertNotContains('privateProperty', $completions);
    }

    public function testReturnsAllPropertiesRegardlessOfPrefix()
    {
        $obj = new class() {
            public $apple = 1;
            public $apricot = 2;
            public $banana = 3;
            public $avocado = 4;
        };

        $analysis1 = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            'a',
            '$obj',
            null,
            $obj
        );

        $analysis2 = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            'xyz',
            '$obj',
            null,
            $obj
        );

        $completions1 = $this->source->getCompletions($analysis1);
        $completions2 = $this->source->getCompletions($analysis2);

        $this->assertEquals($completions1, $completions2);

        $this->assertContains('apple', $completions1);
        $this->assertContains('apricot', $completions1);
        $this->assertContains('avocado', $completions1);
        $this->assertContains('banana', $completions1);
    }

    public function testGetCompletionsWithNoLeftSide()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
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
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$arr',
            'array',
            ['not' => 'an object']
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testGetCompletionsWithInvalidClassName()
    {
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            'NonExistentClass'
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testRuntimeReflectionPreferredOverStatic()
    {
        // Create an object with dynamic properties
        $obj = new \stdClass();
        $obj->runtimeProp = 'added at runtime';

        // Provide both runtime value and type
        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',
            '$obj',
            'stdClass',
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        $this->assertContains('runtimeProp', $completions);
    }

    public function testEmptyPrefixReturnsAllProperties()
    {
        $obj = new class() {
            public $prop1 = 1;
            public $prop2 = 2;
            public $prop3 = 3;
        };

        $analysis = new AnalysisResult(
            CompletionKind::OBJECT_PROPERTY,
            '',  // Empty prefix
            '$obj',
            null,
            $obj
        );

        $completions = $this->source->getCompletions($analysis);

        // Should return all properties
        $this->assertCount(3, $completions);
        $this->assertContains('prop1', $completions);
        $this->assertContains('prop2', $completions);
        $this->assertContains('prop3', $completions);
    }
}
