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
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\Key;
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
    private function createTerminalWithKeys(array $keys): Terminal
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('readKey')->willReturnOnConsecutiveCalls(...$keys);
        $terminal->method('getWidth')->willReturn(80);
        $terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $terminal->method('format')->willReturnCallback(static function (string $text): string {
            return $text;
        });

        return $terminal;
    }

    public function testReadlineReturnsKnownCommandImmediately(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('h', Key::TYPE_CHAR),
            new Key('e', Key::TYPE_CHAR),
            new Key('l', Key::TYPE_CHAR),
            new Key('p', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $shell = $this->getMockBuilder(Shell::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has'])
            ->getMock();
        $shell->expects($this->atLeastOnce())
            ->method('has')
            ->with('help')
            ->willReturn(true);

        $readline = new Readline($terminal);
        $readline->setShell($shell);

        $this->assertSame('help', $readline->readline());
    }

    public function testReadlineShiftEnterInsertsLineBreakWithoutExecuting(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('e', Key::TYPE_CHAR),
            new Key('c', Key::TYPE_CHAR),
            new Key('h', Key::TYPE_CHAR),
            new Key('o', Key::TYPE_CHAR),
            new Key(' ', Key::TYPE_CHAR),
            new Key('1', Key::TYPE_CHAR),
            new Key("\033[13;2u", Key::TYPE_ESCAPE), // Shift+Enter (CSI-u)
            new Key('+', Key::TYPE_CHAR),
            new Key('2', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame("echo 1\n+2", $readline->readline());
    }

    public function testReadlineEscEnterRemapInsertsLineBreakWithoutExecuting(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('e', Key::TYPE_CHAR),
            new Key('c', Key::TYPE_CHAR),
            new Key('h', Key::TYPE_CHAR),
            new Key('o', Key::TYPE_CHAR),
            new Key(' ', Key::TYPE_CHAR),
            new Key('1', Key::TYPE_CHAR),
            new Key("\033\r", Key::TYPE_ESCAPE), // Shift+Enter remapped as Esc+Enter
            new Key('+', Key::TYPE_CHAR),
            new Key('2', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame("echo 1\n+2", $readline->readline());
    }

    public function testReadlineExecutesCompletedMultilinePaste(): void
    {
        $snippet = "function demo() {\n    return 42;\n}";
        $terminal = $this->createTerminalWithKeys([
            new Key($snippet, Key::TYPE_PASTE),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame($snippet, $readline->readline());
        $this->assertFalse($readline->isMultilineMode());
    }

    public function testReadlineReturnsFalseOnEof(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('', Key::TYPE_EOF),
        ]);

        $readline = new Readline($terminal);

        $this->assertFalse($readline->readline());
    }

    public function testUpArrowMovesWithinSoftWrappedLineBeforeHistory(): void
    {
        $text = \str_repeat('a', 100);
        $terminal = $this->createTerminalWithKeys([
            new Key($text, Key::TYPE_PASTE),
            new Key("\033[A", Key::TYPE_ESCAPE), // Up arrow
            new Key('X', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $expected = \str_repeat('a', 20).'X'.\str_repeat('a', 80);
        $this->assertSame($expected, $readline->readline());
    }

    public function testDownArrowMovesWithinSoftWrappedLineBeforeHistory(): void
    {
        $text = \str_repeat('a', 100);
        $terminal = $this->createTerminalWithKeys([
            new Key($text, Key::TYPE_PASTE),
            new Key("\033[A", Key::TYPE_ESCAPE), // Up arrow
            new Key("\033[B", Key::TYPE_ESCAPE), // Down arrow
            new Key('X', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $readline = new Readline($terminal);

        $this->assertSame($text.'X', $readline->readline());
    }

    public function testSearchModeReplaysUnhandledKeyToMainLoop(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key("\x12", Key::TYPE_CONTROL), // Ctrl-R
            new Key('a', Key::TYPE_CHAR),
            new Key('b', Key::TYPE_CHAR),
            new Key('c', Key::TYPE_CHAR),
            new Key("\033[D", Key::TYPE_ESCAPE), // Left arrow (replayed)
            new Key('X', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add("'abc'");

        $readline = new Readline($terminal, null, $history);

        $this->assertSame("'abcX'", $readline->readline());
    }

    public function testSuggestionDoesNotLeakAcrossReadlineCalls(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('f', Key::TYPE_CHAR),
            new Key('o', Key::TYPE_CHAR),
            new Key('o', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
            new Key("\033[C", Key::TYPE_ESCAPE), // Right arrow
            new Key("\n", Key::TYPE_CHAR),
        ]);

        $history = new History();
        $history->add('foobar');

        $readline = new Readline($terminal, null, $history);

        $this->assertSame('foo', $readline->readline());
        $this->assertSame('', $readline->readline());
    }

    public function testTabMenuReplaysUnhandledKeyToMainLoop(): void
    {
        $terminal = $this->createTerminalWithKeys([
            new Key('$', Key::TYPE_CHAR),
            new Key('t', Key::TYPE_CHAR),
            new Key('e', Key::TYPE_CHAR),
            new Key("\t", Key::TYPE_CONTROL), // Tab
            new Key("\x01", Key::TYPE_CONTROL), // Ctrl-A (unknown in menu, replayed)
            new Key('+', Key::TYPE_CHAR),
            new Key("\n", Key::TYPE_CHAR),
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
