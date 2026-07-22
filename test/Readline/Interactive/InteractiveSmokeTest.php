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
use Psy\Completion\CompletionEngine;
use Psy\Completion\Source\VariableSource;
use Psy\Context;
use Psy\Readline\Interactive\Actions\TabAction;
use Psy\Readline\Interactive\Input\EofEvent;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Input\MouseEvent;
use Psy\Readline\Interactive\Input\PasteEvent;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class InteractiveSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }
    }

    /**
     * @return Terminal&MockObject
     */
    private function createTerminalWithEvents(array $events): Terminal
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('readEvent')->willReturnOnConsecutiveCalls(...$events);
        $terminal->method('getWidth')->willReturn(80);
        $terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $terminal->method('format')->willReturnCallback(static function (string $text): string {
            return $text;
        });

        return $terminal;
    }

    public function testReadlineReturnsKnownCommandImmediately(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('h', KeyEvent::TYPE_CHAR),
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent('l', KeyEvent::TYPE_CHAR),
            new KeyEvent('p', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $shell = $this->getMockBuilder(Shell::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has'])
            ->getMock();
        $shell->expects($this->atLeastOnce())
            ->method('has')
            ->willReturnCallback(static fn (string $name): bool => $name === 'help');

        $readline = new Readline($terminal);
        $readline->setShell($shell);

        $this->assertSame('help', $readline->readline());
    }

    public function testReadlineReturnsKnownCommandWithArgumentsImmediately(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('h', KeyEvent::TYPE_CHAR),
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent('l', KeyEvent::TYPE_CHAR),
            new KeyEvent('p', KeyEvent::TYPE_CHAR),
            new KeyEvent(' ', KeyEvent::TYPE_CHAR),
            new KeyEvent('l', KeyEvent::TYPE_CHAR),
            new KeyEvent('s', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
            new EofEvent(),
        ]);

        $shell = $this->getMockBuilder(Shell::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has'])
            ->getMock();
        $shell->expects($this->atLeastOnce())
            ->method('has')
            ->willReturnCallback(static fn (string $name): bool => $name === 'help');

        $readline = new Readline($terminal);
        $readline->setShell($shell);

        $this->assertSame('help ls', $readline->readline());
    }

    public function testReadlineShiftEnterInsertsLineBreakWithoutExecuting(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent('c', KeyEvent::TYPE_CHAR),
            new KeyEvent('h', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent(' ', KeyEvent::TYPE_CHAR),
            new KeyEvent('1', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[13;2u", KeyEvent::TYPE_ESCAPE), // Shift+Enter (CSI-u)
            new KeyEvent('+', KeyEvent::TYPE_CHAR),
            new KeyEvent('2', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame("echo 1\n+2", $readline->readline());
    }

    public function testReadlineEscEnterRemapInsertsLineBreakWithoutExecuting(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent('c', KeyEvent::TYPE_CHAR),
            new KeyEvent('h', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent(' ', KeyEvent::TYPE_CHAR),
            new KeyEvent('1', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033\r", KeyEvent::TYPE_ESCAPE), // Shift+Enter remapped as Esc+Enter
            new KeyEvent('+', KeyEvent::TYPE_CHAR),
            new KeyEvent('2', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame("echo 1\n+2", $readline->readline());
    }

    public function testReadlineExecutesCompletedMultilinePaste(): void
    {
        $snippet = "function demo() {\n    return 42;\n}";
        $terminal = $this->createTerminalWithEvents([
            new PasteEvent($snippet),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame($snippet, $readline->readline());
        $this->assertFalse($readline->isMultilineMode());
    }

    public function testReadlineClearsInputFrameErrorOnPaste(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new PasteEvent("echo 'fixed';"),
            new EofEvent(),
        ]);

        /** @var Readline&MockObject $readline */
        $readline = $this->getMockBuilder(Readline::class)
            ->setConstructorArgs([$terminal])
            ->onlyMethods(['setInputFrameError'])
            ->getMock();

        $readline->expects($this->once())
            ->method('setInputFrameError')
            ->with(false);

        $this->assertFalse($readline->readline());
    }

    public function testReadlineReturnsFalseOnEof(): void
    {
        $writes = [];
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('readEvent')->willReturn(new EofEvent());
        $terminal->method('getWidth')->willReturn(80);
        $terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $terminal->method('format')->willReturnCallback(static function (string $text): string {
            return $text;
        });
        $terminal->method('write')->willReturnCallback(function (string $text) use (&$writes): void {
            $writes[] = $text;
        });

        $readline = new Readline($terminal);

        $this->assertFalse($readline->readline());
        $this->assertContains("\n\n", $writes);
    }

    public function testReadlineIgnoresDelayedMouseEvents(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 1, 1),
            new KeyEvent('a', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame('a', $readline->readline());
    }

    public function testUpArrowMovesWithinSoftWrappedLineBeforeHistory(): void
    {
        $text = \str_repeat('a', 100);
        $terminal = $this->createTerminalWithEvents([
            new PasteEvent($text),
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up arrow
            new KeyEvent('X', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $expected = \str_repeat('a', 20).'X'.\str_repeat('a', 80);
        $this->assertSame($expected, $readline->readline());
    }

    public function testDownArrowMovesWithinSoftWrappedLineBeforeHistory(): void
    {
        $text = \str_repeat('a', 100);
        $terminal = $this->createTerminalWithEvents([
            new PasteEvent($text),
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up arrow
            new KeyEvent("\033[B", KeyEvent::TYPE_ESCAPE), // Down arrow
            new KeyEvent('X', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame($text.'X', $readline->readline());
    }

    public function testUpArrowFiltersHistoryByCurrentInput(): void
    {
        $terminal = $this->createTerminalWithEvents([
            // Type "echo" then press up twice, then enter
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent('c', KeyEvent::TYPE_CHAR),
            new KeyEvent('h', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up arrow, should get 'echo "world"'
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up arrow, should skip '$foo' and get 'echo "hello"'
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');
        $history->add('echo "world"');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('echo "hello"', $readline->readline());
    }

    public function testUpDownArrowFilteredNavigationRestoresInput(): void
    {
        $terminal = $this->createTerminalWithEvents([
            // Type "42", press up to match, then down restores input, then submit
            new KeyEvent('4', KeyEvent::TYPE_CHAR),
            new KeyEvent('2', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up, '$foo = 42'
            new KeyEvent("\033[B", KeyEvent::TYPE_ESCAPE), // Down, restores "42"
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('echo "hello"');
        $history->add('$foo = 42');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('42', $readline->readline());
    }

    public function testUpArrowWithEmptyInputBrowsesAllHistory(): void
    {
        $terminal = $this->createTerminalWithEvents([
            // Empty input, press up twice, then enter
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up, 'second'
            new KeyEvent("\033[A", KeyEvent::TYPE_ESCAPE), // Up, 'first'
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('first');
        $history->add('second');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('first', $readline->readline());
    }

    public function testSearchModeLeftArrowAcceptsWithCursorAtStart(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent("\x12", KeyEvent::TYPE_CONTROL), // Ctrl-R
            new KeyEvent('4', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[D", KeyEvent::TYPE_ESCAPE), // Left arrow: accept, cursor at start
            new KeyEvent('!', KeyEvent::TYPE_CHAR),        // Prepend "!" at cursor 0 -> "!42"
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('42');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('!42', $readline->readline());
    }

    public function testSearchModeRightArrowAcceptsWithCursorAtEnd(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent("\x12", KeyEvent::TYPE_CONTROL), // Ctrl-R
            new KeyEvent('4', KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE), // Right arrow: accept, cursor at end
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('42');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('42', $readline->readline());
    }

    public function testSearchModeReplaysUnhandledKeyToMainLoop(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent("\x12", KeyEvent::TYPE_CONTROL), // Ctrl-R
            new KeyEvent('4', KeyEvent::TYPE_CHAR),
            new KeyEvent("\x01", KeyEvent::TYPE_CONTROL), // Ctrl-A (replayed as move-to-start)
            new KeyEvent('!', KeyEvent::TYPE_CHAR),        // Prepend "!" at cursor 0 -> "!42"
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('42');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('!42', $readline->readline());
    }

    public function testSearchModeClearsStaleSuggestionOnEnter(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('f', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent("\x12", KeyEvent::TYPE_CONTROL), // Ctrl-R
            new KeyEvent("\x1b", KeyEvent::TYPE_CONTROL), // Escape: cancel search
            new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE), // Right arrow: should not accept stale suggestion
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('foobar');

        $readline = new Readline($terminal, null, $history);
        $readline->setUseSuggestions(true);

        $this->assertSame('foo', $readline->readline());
    }

    public function testSuggestionDoesNotLeakAcrossReadlineCalls(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('f', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent('o', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
            new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE), // Right arrow
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('foobar');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('foo', $readline->readline());
        $this->assertSame('', $readline->readline());
    }

    public function testTabMenuReplaysUnhandledKeyToMainLoop(): void
    {
        $terminal = $this->createTerminalWithEvents([
            new KeyEvent('$', KeyEvent::TYPE_CHAR),
            new KeyEvent('t', KeyEvent::TYPE_CHAR),
            new KeyEvent('e', KeyEvent::TYPE_CHAR),
            new KeyEvent("\t", KeyEvent::TYPE_CONTROL), // Tab
            new KeyEvent("\x01", KeyEvent::TYPE_CONTROL), // Ctrl-A (unknown in menu, replayed)
            new KeyEvent('+', KeyEvent::TYPE_CHAR),
            new KeyEvent("\n", KeyEvent::TYPE_CHAR),
        ]);

        $context = new Context();
        $context->setAll([
            'testOne' => 1,
            'testTwo' => 2,
        ]);

        $completion = new CompletionEngine($context);
        $completion->addSource(new VariableSource($context));

        $readline = new Readline($terminal);
        $readline->setCompletionEngine($completion);

        $tabAction = $readline->getTabAction();
        $this->assertInstanceOf(TabAction::class, $tabAction);
        $tabAction->setInteractiveSelectionEnabled(true);

        $this->assertSame('+$test', $readline->readline());
    }
}
