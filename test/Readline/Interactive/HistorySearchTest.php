<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive;

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Output\Theme;
use Psy\Readline\Interactive\Actions\ReverseSearchAction;
use Psy\Readline\Interactive\HistorySearch;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\Key;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\OverlayViewport;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class HistorySearchTest extends TestCase
{
    use BufferAssertionTrait;
    private History $history;

    /** @var Terminal&MockObject */
    private Terminal $terminal;
    private HistorySearch $search;
    private Readline $readline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = new History();
        $this->history->add('function bar() { echo "bar"; }');
        $this->history->add('echo "goodbye"');
        $this->history->add('$bar = "test string"');
        $this->history->add('function foo() { return 42; }');
        $this->history->add('echo "hello world"');

        $this->terminal = $this->createMock(Terminal::class);

        $this->readline = new Readline($this->terminal, null, $this->history);
        $this->search = $this->readline->getSearch();
    }

    public function testHistorySearch(): void
    {
        $matches = $this->history->search('echo');
        $this->assertCount(3, $matches);
        $this->assertSame('echo "hello world"', $matches[0]);
        $this->assertSame('echo "goodbye"', $matches[1]);
        $this->assertSame('function bar() { echo "bar"; }', $matches[2]);

        $matches = $this->history->search('function');
        $this->assertCount(2, $matches);
        $this->assertSame('function foo() { return 42; }', $matches[0]);
        $this->assertSame('function bar() { echo "bar"; }', $matches[1]);

        $matches = $this->history->search('nonexistent');
        $this->assertCount(0, $matches);

        // Empty search returns all entries
        $matches = $this->history->search('');
        $this->assertCount(5, $matches);
    }

    public function testHistorySearchReverse(): void
    {
        $matches = $this->history->search('echo', true);
        $this->assertCount(3, $matches);
        $this->assertSame('function bar() { echo "bar"; }', $matches[0]);
        $this->assertSame('echo "goodbye"', $matches[1]);
        $this->assertSame('echo "hello world"', $matches[2]);
    }

    public function testHistorySearchSmartCase(): void
    {
        // All lowercase: case-insensitive
        $matches = $this->history->search('echo');
        $this->assertCount(3, $matches);

        $matches = $this->history->search('function');
        $this->assertCount(2, $matches);

        // Contains uppercase: case-sensitive
        $matches = $this->history->search('ECHO');
        $this->assertCount(0, $matches);

        $matches = $this->history->search('Function');
        $this->assertCount(0, $matches);

        // Mixed case matching actual casing
        $matches = $this->history->search('Hello');
        $this->assertCount(0, $matches);

        $matches = $this->history->search('hello');
        $this->assertCount(1, $matches);
    }

    public function testSearchMode(): void
    {
        $this->assertFalse($this->search->isActive());

        $this->search->enter();
        $this->assertTrue($this->search->isActive());
        $this->assertSame('', $this->search->getQuery());

        $this->search->exit();
        $this->assertFalse($this->search->isActive());
    }

    public function testSearchQueryUpdate(): void
    {
        $this->search->enter();

        $this->search->updateQuery('e');
        $this->assertSame('e', $this->search->getQuery());

        $this->search->updateQuery('ec');
        $this->assertSame('ec', $this->search->getQuery());

        $this->search->updateQuery('ech');
        $this->assertSame('ech', $this->search->getQuery());

        $this->search->updateQuery('echo');
        $this->assertSame('echo', $this->search->getQuery());

        $this->search->updateQuery('ech');
        $this->assertSame('ech', $this->search->getQuery());

        $this->search->updateQuery('function');
        $this->assertSame('function', $this->search->getQuery());
    }

    public function testSearchMatches(): void
    {
        $this->search->enter();

        // Entering search mode with empty query shows all history (newest first)
        $this->assertSame('echo "hello world"', $this->search->getSelectedMatch());

        $this->search->updateQuery('goodbye');
        $match = $this->search->getSelectedMatch();
        $this->assertSame('echo "goodbye"', $match);

        // Only one match, so next wraps around and rings the bell
        $this->terminal->expects($this->once())->method('bell');
        $this->search->findNext();
        $match = $this->search->getSelectedMatch();
        $this->assertSame('echo "goodbye"', $match);
    }

    public function testSearchMaxRowsDoesNotDoubleCountSearchPrompt(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(30);

        $history = new History();
        for ($i = 0; $i < 20; $i++) {
            $history->add('cmd-'.$i);
        }

        $viewport = new OverlayViewport($terminal);
        $frameRenderer = new FrameRenderer($terminal, $viewport);
        $search = new HistorySearch($terminal, $history, $frameRenderer, $viewport, new Theme());

        $search->enter();
        $search->updateQuery('');

        $viewport->setInputRowCount(10);

        // With 25 matches and limited viewport, search should truncate.
        // Verify the match count reflects all items were found.
        $this->assertSame(20, $search->getMatchCount());

        // The selected match should still be valid after entering search
        $this->assertNotNull($search->getSelectedMatch());
        $this->assertSame(0, $search->getSelectedIndex());
    }

    public function testSearchNavigation(): void
    {
        $this->search->enter();
        $this->search->updateQuery('function');

        // Newest first: foo was added after bar
        $this->assertSame('function foo() { return 42; }', $this->search->getSelectedMatch());

        $this->search->findNext();
        $this->assertSame('function bar() { echo "bar"; }', $this->search->getSelectedMatch());

        $this->search->findPrevious();
        $this->assertSame('function foo() { return 42; }', $this->search->getSelectedMatch());

        // Wrap around backwards rings the bell
        $this->terminal->expects($this->once())->method('bell');
        $this->search->findPrevious();
        $this->assertSame('function bar() { echo "bar"; }', $this->search->getSelectedMatch());
    }

    public function testAcceptSearchMatch(): void
    {
        $buffer = new Buffer();
        $buffer->insert('original text');

        $this->search->saveBuffer($buffer);
        $this->search->enter();

        $this->search->updateQuery('goodbye');

        // Accept by simulating Enter key
        $match = $this->search->getSelectedMatch();
        $this->assertSame('echo "goodbye"', $match);

        $buffer->clear();
        $buffer->insert($match);
        $this->search->exit();

        $this->assertSame('echo "goodbye"', $buffer->getText());
        $this->assertFalse($this->search->isActive());
    }

    public function testCancelSearchRestoresBuffer(): void
    {
        $buffer = new Buffer();
        $buffer->insert('original text');
        $this->setBufferState($buffer, 'origi<cursor>nal text');

        $this->search->saveBuffer($buffer);
        $this->search->enter();
        $this->search->updateQuery('echo');

        // Cancel via handleInput (Escape key)
        $key = new Key("\x1b", Key::TYPE_CONTROL);
        $this->search->handleInput($key, $buffer);

        $this->assertBufferState('origi<cursor>nal text', $buffer);
        $this->assertFalse($this->search->isActive());
    }

    public function testReverseSearchAction(): void
    {
        $buffer = new Buffer();
        $action = new ReverseSearchAction($this->search);

        $this->assertFalse($this->search->isActive());
        $result = $action->execute($buffer, $this->terminal, $this->readline);
        $this->assertTrue($result);
        $this->assertTrue($this->search->isActive());
    }

    public function testReverseSearchActionPrefillsFromCurrentLineInMultilineBuffer(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, "function test() {\n    retur<cursor>n 'multi';\n}");
        $action = new ReverseSearchAction($this->search);

        $result = $action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertTrue($this->search->isActive());
        $this->assertSame("    return 'multi';", $this->search->getQuery());
        $this->assertStringNotContainsString("\n", $this->search->getQuery());
    }

    public function testMultiLineCommandSearch(): void
    {
        $multiline = "function test() {\n    return 'multi';\n}";
        $this->history->add($multiline);

        $this->search->enter();
        $this->search->updateQuery('multi');

        $match = $this->search->getSelectedMatch();
        $this->assertSame($multiline, $match);
    }
}
