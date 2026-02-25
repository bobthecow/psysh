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
use Psy\Readline\Interactive\Actions\ReverseSearchAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\KeyBindings;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class HistorySearchTest extends TestCase
{
    use BufferAssertionTrait;
    private History $history;

    /** @var Terminal&MockObject */
    private Terminal $terminal;
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

        $bindings = KeyBindings::createDefault($this->history);
        $this->readline = new Readline($this->terminal, $bindings, $this->history);
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

    public function testHistorySearchCaseInsensitive(): void
    {
        $matches = $this->history->search('ECHO');
        $this->assertCount(3, $matches);

        $matches = $this->history->search('Function');
        $this->assertCount(2, $matches);
    }

    public function testSearchMode(): void
    {
        $this->assertFalse($this->readline->isInSearchMode());

        $this->readline->enterSearchMode();
        $this->assertTrue($this->readline->isInSearchMode());
        $this->assertSame('', $this->readline->getSearchQuery());

        $this->readline->exitSearchMode();
        $this->assertFalse($this->readline->isInSearchMode());
    }

    public function testSearchQueryUpdate(): void
    {
        $this->readline->enterSearchMode();

        $this->readline->addSearchChar('e');
        $this->assertSame('e', $this->readline->getSearchQuery());

        $this->readline->addSearchChar('c');
        $this->assertSame('ec', $this->readline->getSearchQuery());

        $this->readline->addSearchChar('h');
        $this->assertSame('ech', $this->readline->getSearchQuery());

        $this->readline->addSearchChar('o');
        $this->assertSame('echo', $this->readline->getSearchQuery());

        $this->readline->removeSearchChar();
        $this->assertSame('ech', $this->readline->getSearchQuery());

        $this->readline->updateSearchQuery('function');
        $this->assertSame('function', $this->readline->getSearchQuery());
    }

    public function testSearchMatches(): void
    {
        $this->readline->enterSearchMode();

        $this->assertNull($this->readline->getCurrentSearchMatch());

        $this->readline->updateSearchQuery('goodbye');
        $match = $this->readline->getCurrentSearchMatch();
        $this->assertSame('echo "goodbye"', $match);

        // Only one match, so next wraps around and rings the bell
        $this->terminal->expects($this->once())->method('bell');
        $this->readline->findNextSearchMatch();
        $match = $this->readline->getCurrentSearchMatch();
        $this->assertSame('echo "goodbye"', $match);
    }

    public function testSearchNavigation(): void
    {
        $this->readline->enterSearchMode();
        $this->readline->updateSearchQuery('function');

        $this->assertSame('function bar() { echo "bar"; }', $this->readline->getCurrentSearchMatch());

        $this->readline->findNextSearchMatch();
        $this->assertSame('function foo() { return 42; }', $this->readline->getCurrentSearchMatch());

        $this->readline->findPreviousSearchMatch();
        $this->assertSame('function bar() { echo "bar"; }', $this->readline->getCurrentSearchMatch());

        // Wrap around backwards rings the bell
        $this->terminal->expects($this->once())->method('bell');
        $this->readline->findPreviousSearchMatch();
        $this->assertSame('function foo() { return 42; }', $this->readline->getCurrentSearchMatch());
    }

    public function testAcceptSearchMatch(): void
    {
        $buffer = new Buffer();
        $buffer->insert('original text');

        $this->readline->saveBufferForSearch($buffer);
        $this->readline->enterSearchMode();

        $this->readline->updateSearchQuery('goodbye');
        $this->readline->acceptSearchMatch($buffer);

        $this->assertSame('echo "goodbye"', $buffer->getText());
        $this->assertFalse($this->readline->isInSearchMode());
    }

    public function testCancelSearchRestoresBuffer(): void
    {
        $buffer = new Buffer();
        $buffer->insert('original text');
        $this->setBufferState($buffer, 'origi<cursor>nal text');

        $this->readline->saveBufferForSearch($buffer);
        $this->readline->enterSearchMode();

        $this->readline->updateSearchQuery('echo');

        $this->readline->cancelSearch($buffer);

        $this->assertBufferState('origi<cursor>nal text', $buffer);
        $this->assertFalse($this->readline->isInSearchMode());
    }

    public function testReverseSearchAction(): void
    {
        $buffer = new Buffer();
        $action = new ReverseSearchAction($this->history);

        $this->assertFalse($this->readline->isInSearchMode());
        $result = $action->execute($buffer, $this->terminal, $this->readline);
        $this->assertTrue($result);
        $this->assertTrue($this->readline->isInSearchMode());
    }

    public function testMultiLineCommandSearch(): void
    {
        $multiline = "function test() {\n    return 'multi';\n}";
        $this->history->add($multiline);

        $this->readline->enterSearchMode();
        $this->readline->updateSearchQuery('multi');

        $match = $this->readline->getCurrentSearchMatch();
        $this->assertSame($multiline, $match);

        $buffer = new Buffer();
        $this->readline->acceptSearchMatch($buffer);
        $this->assertSame($multiline, $buffer->getText());
    }
}
