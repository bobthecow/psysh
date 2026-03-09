<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Suggestion;

use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Suggestion\SuggestionEngine;
use Psy\Test\TestCase;

class SuggestionEngineTest extends TestCase
{
    public function testGetSuggestionFromHistory()
    {
        $history = new History();
        $history->add('$count = count($items)');
        $history->add('$config = compact("key")');
        $history->add('$colors = compact("red", "blue")');

        $engine = new SuggestionEngine($history);

        // Should suggest most recent match
        $result = $engine->getSuggestion('$col', 4);

        $this->assertNotNull($result);
        $this->assertEquals('ors = compact("red", "blue")', $result->getDisplayText());
        $this->assertEquals('history', $result->getSource());
        $this->assertEquals('$colors = compact("red", "blue")', $result->applyToBuffer('$col'));
    }

    public function testNoSuggestionWhenCursorNotAtEnd()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $engine = new SuggestionEngine($history);

        $result = $engine->getSuggestion('$na', 2);

        $this->assertNull($result);
    }

    public function testNoSuggestionForEmptyBuffer()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $engine = new SuggestionEngine($history);

        $result = $engine->getSuggestion('', 0);

        $this->assertNull($result);
    }

    public function testNoSuggestionWhenNoMatch()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $engine = new SuggestionEngine($history);

        $result = $engine->getSuggestion('array_sum([1])', 14);

        $this->assertNull($result);
    }

    public function testCaching()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $engine = new SuggestionEngine($history);

        $result1 = $engine->getSuggestion('$na', 3);

        $result2 = $engine->getSuggestion('$na', 3);

        $this->assertSame($result1, $result2);
    }

    public function testCacheClearsOnBufferChange()
    {
        $history = new History();
        $history->add('$name = "Alice"');
        $history->add('$names = ["Alice"]');

        $engine = new SuggestionEngine($history);

        $result1 = $engine->getSuggestion('$name', 5);
        $this->assertEquals('s = ["Alice"]', $result1->getDisplayText());

        $result2 = $engine->getSuggestion('$name ', 6);
        $this->assertEquals('= "Alice"', $result2->getDisplayText());

        $this->assertNotSame($result1, $result2);
    }

    public function testClearCache()
    {
        $history = new History();
        $history->add('$name = "Alice"');

        $engine = new SuggestionEngine($history);

        $result1 = $engine->getSuggestion('$na', 3);
        $this->assertNotNull($result1);

        $engine->clearCache();

        $result2 = $engine->getSuggestion('$na', 3);
        $this->assertNotNull($result2);

        $this->assertNotSame($result1, $result2);
    }
}
