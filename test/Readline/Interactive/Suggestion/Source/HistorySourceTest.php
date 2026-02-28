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

use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Suggestion\Source\HistorySource;
use Psy\Test\TestCase;

class HistorySourceTest extends TestCase
{
    public function testExactPrefixMatch()
    {
        $history = new History();
        $history->add('$name = "Alice"');
        $history->add('$names = ["Alice", "Bob"]');

        $source = new HistorySource($history);

        $result = $source->getSuggestion('$name', \mb_strlen('$name'));

        $this->assertNotNull($result);
        $this->assertEquals('s = ["Alice", "Bob"]', $result->getText());
        $this->assertEquals('$names = ["Alice", "Bob"]', $result->getFullText());
        $this->assertEquals('history', $result->getSource());
    }

    public function testNoMatchReturnsNull()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $source = new HistorySource($history);

        $result = $source->getSuggestion('array_sum', \mb_strlen('array_sum'));

        $this->assertNull($result);
    }

    public function testDoesNotSuggestIdenticalCommand()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $source = new HistorySource($history);

        // Exact match should return null
        $result = $source->getSuggestion('$name = "Alice"', \mb_strlen('$name = "Alice"'));

        $this->assertNull($result);
    }

    public function testMultiLineCommandCollapsedToSingleLine()
    {
        $history = new History();
        $history->add("function test() {\n    echo 'hello';\n}");

        $source = new HistorySource($history);

        $result = $source->getSuggestion('function', \mb_strlen('function'));

        $this->assertNotNull($result);
        $this->assertStringNotContainsString("\n", $result->getText());
    }

    public function testGetPriority()
    {
        $history = new History();
        $source = new HistorySource($history);

        $this->assertEquals(100, $source->getPriority());
    }
}
