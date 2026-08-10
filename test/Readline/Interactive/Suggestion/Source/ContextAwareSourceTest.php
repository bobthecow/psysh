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

use Psy\Completion\CompletionEngine;
use Psy\Readline\Interactive\Suggestion\Source\ContextAwareSource;
use Psy\Test\TestCase;

class ContextAwareSourceTest extends TestCase
{
    /**
     * @dataProvider emptyPrefixProvider
     */
    public function testSuggestsTheEntireCompletionForEmptyPrefixes(string $buffer)
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['team']);

        $result = (new ContextAwareSource($completer))->getSuggestion($buffer, \strlen($buffer));

        $this->assertNotNull($result);
        $this->assertSame('team', $result->getDisplayText());
        $this->assertSame($buffer.'team', $result->applyToBuffer($buffer));
    }

    public static function emptyPrefixProvider(): array
    {
        return [
            'variable'        => ['$'],
            'object member'   => ['$object->'],
            'static member'   => ['Example::'],
        ];
    }

    public function testSuggestsTheSuffixForPrefixMatches()
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['team']);

        $result = (new ContextAwareSource($completer))->getSuggestion('$te', 3);

        $this->assertNotNull($result);
        $this->assertSame('am', $result->getDisplayText());
        $this->assertSame('$team', $result->applyToBuffer('$te'));
    }

    public function testSuppressesNonPrefixFuzzyMatches()
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['team']);

        $this->assertNull((new ContextAwareSource($completer))->getSuggestion('$tm', 3));
    }

    public function testCompleterCanBeReplaced()
    {
        $first = $this->createMock(CompletionEngine::class);
        $first->method('getCompletions')->willReturn(['team']);
        $second = $this->createMock(CompletionEngine::class);
        $second->method('getCompletions')->willReturn(['test']);

        $source = new ContextAwareSource($first);
        $source->setCompleter($second);
        $result = $source->getSuggestion('$t', 2);

        $this->assertNotNull($result);
        $this->assertSame('est', $result->getDisplayText());
        $this->assertSame('$test', $result->applyToBuffer('$t'));
    }
}
