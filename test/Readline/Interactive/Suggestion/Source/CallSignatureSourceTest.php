<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Suggestion\Source;

use Psy\Readline\Interactive\Suggestion\Source\CallSignatureSource;
use Psy\Test\TestCase;

class CallSignatureSourceTest extends TestCase
{
    private CallSignatureSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = new CallSignatureSource();
    }

    public function testSuggestsParametersForBuiltInFunction()
    {
        $result = $this->source->getSuggestion('array_merge(', \mb_strlen('array_merge('));

        $this->assertNotNull($result);
        $this->assertStringContainsString('$', $result->getDisplayText());
        $this->assertEquals('call-signature', $result->getSource());
    }

    public function testArrayMapSignature()
    {
        $result = $this->source->getSuggestion('array_map(', \mb_strlen('array_map('));

        $this->assertNotNull($result);
        // array_map($callback, $array, ...$arrays)
        $this->assertStringContainsString('callback', $result->getDisplayText());
        $this->assertStringContainsString('array', $result->getDisplayText());
    }

    public function testStrReplaceSignature()
    {
        $result = $this->source->getSuggestion('str_replace(', \mb_strlen('str_replace('));

        $this->assertNotNull($result);
        // str_replace($search, $replace, $subject, &$count = null)
        $this->assertStringContainsString('search', $result->getDisplayText());
        $this->assertStringContainsString('replace', $result->getDisplayText());
        $this->assertStringContainsString('subject', $result->getDisplayText());
    }

    public function testNoSuggestionWhenNotInEmptyParens()
    {
        $result = $this->source->getSuggestion('array_merge(something', \mb_strlen('array_merge(something'));

        $this->assertNull($result);
    }

    public function testNoSuggestionForNonFunction()
    {
        $result = $this->source->getSuggestion('notAFunction(', \mb_strlen('notAFunction('));

        $this->assertNull($result);
    }

    public function testNoSuggestionForVariables()
    {
        $result = $this->source->getSuggestion('$variable', \mb_strlen('$variable'));

        $this->assertNull($result);
    }

    public function testNoSuggestionForMethodCalls()
    {
        $result = $this->source->getSuggestion('$obj->count(', \mb_strlen('$obj->count('));

        $this->assertNull($result);
    }

    public function testNoSuggestionForStaticMethodCalls()
    {
        $result = $this->source->getSuggestion('Foo::count(', \mb_strlen('Foo::count('));

        $this->assertNull($result);
    }

    public function testHandlesWhitespaceInParens()
    {
        $result = $this->source->getSuggestion('array_merge( ', \mb_strlen('array_merge( '));

        $this->assertNotNull($result);
        $this->assertStringContainsString('$', $result->getDisplayText());
    }

    public function testGetPriority()
    {
        $priority = $this->source->getPriority();

        $this->assertEquals(150, $priority);
    }

    public function testHandlesOptionalParameters()
    {
        $result = $this->source->getSuggestion('substr(', \mb_strlen('substr('));

        $this->assertNotNull($result);

        if (\version_compare(\PHP_VERSION, '8.0', '<')) {
            // PHP 7.4: substr($str, $start, $length = null)
            $this->assertStringContainsString('str', $result->getDisplayText());
            $this->assertStringContainsString('start', $result->getDisplayText());
        } else {
            // PHP 8.0+: substr($string, $offset, $length = null)
            $this->assertStringContainsString('string', $result->getDisplayText());
            $this->assertStringContainsString('offset', $result->getDisplayText());
        }

        $this->assertStringContainsString('=', $result->getDisplayText());
    }

    public function testHandlesVariadicParameters()
    {
        // printf($format, ...$values)
        $result = $this->source->getSuggestion('printf(', \mb_strlen('printf('));

        $this->assertNotNull($result);
        $this->assertStringContainsString('format', $result->getDisplayText());
        $this->assertStringContainsString('...', $result->getDisplayText());
    }

    public function testEmptySignatureForNoParameters()
    {
        // Functions with no parameters should return empty string
        // phpversion() has optional parameter, but let's test the concept
        $result = $this->source->getSuggestion('time(', \mb_strlen('time('));

        // time() has no parameters
        if ($result !== null) {
            $this->assertEquals('', $result->getDisplayText());
        } else {
            // Or it might return null, which is also fine
            $this->assertNull($result);
        }
    }
}
