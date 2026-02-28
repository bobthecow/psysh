<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Renderer;

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\OverlayViewport;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class FrameRendererTest extends TestCase
{
    use BufferAssertionTrait;

    private Terminal $terminal;
    private FrameRenderer $renderer;
    private OverlayViewport $viewport;

    /** @var int[] */
    private array $cursorColumns = [];
    /** @var int[] */
    private array $cursorUpMoves = [];
    /** @var int[] */
    private array $cursorDownMoves = [];
    /** @var string[] */
    private array $writes = [];
    private int $clearToEndOfScreenCalls = 0;

    protected function setUp(): void
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }

        $this->terminal = $this->createMock(Terminal::class);
        $this->viewport = new OverlayViewport($this->terminal);
        $this->renderer = new FrameRenderer($this->terminal, $this->viewport);

        $this->cursorColumns = [];
        $this->cursorUpMoves = [];
        $this->cursorDownMoves = [];
        $this->writes = [];
        $this->clearToEndOfScreenCalls = 0;

        $this->terminal->method('moveCursorToColumn')
            ->willReturnCallback(function (int $col): void {
                $this->cursorColumns[] = $col;
            });
        $this->terminal->method('moveCursorUp')
            ->willReturnCallback(function (int $count): void {
                $this->cursorUpMoves[] = $count;
            });
        $this->terminal->method('moveCursorDown')
            ->willReturnCallback(function (int $count): void {
                $this->cursorDownMoves[] = $count;
            });
        $this->terminal->method('write')
            ->willReturnCallback(function (string $text): void {
                $this->writes[] = $text;
            });
        $this->terminal->method('clearToEndOfScreen')
            ->willReturnCallback(function (): void {
                $this->clearToEndOfScreenCalls++;
            });
        $this->terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $this->terminal->method('format')->willReturnCallback(static function (string $text): string {
            return $text;
        });

        $this->terminal->method('getWidth')->willReturn(80);
        $this->terminal->method('getHeight')->willReturn(24);
    }

    /**
     * Regression test: cursor position must use display width, not code points.
     *
     * Full-width CJK characters occupy 2 terminal columns per code point.
     * If the renderer uses code-point count instead of display width, the
     * cursor drifts left by one column per full-width character.
     */
    public function testSingleLineCursorUsesDisplayWidth()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        // "中文" = 2 code points, but 4 display columns
        $this->setBufferState($buffer, '中文<cursor>');

        $this->renderer->render($buffer, false, null);

        // Prompt ">>> " = 4 display columns
        // "中文" = 4 display columns
        // Cursor column = 4 + 4 + 1 = 9
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(9, $lastColumn);
    }

    /**
     * Regression test: same bug but mid-string, after some ASCII.
     */
    public function testSingleLineCursorWithMixedWidthCharacters()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        // "ab中" = 3 code points, but 4 display columns (a=1, b=1, 中=2)
        $this->setBufferState($buffer, 'ab中<cursor>cd');

        $this->renderer->render($buffer, false, null);

        // Prompt = 4, text before cursor "ab中" = 4 display columns
        // Cursor column = 4 + 4 + 1 = 9
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(9, $lastColumn);
    }

    /**
     * Regression test: multiline cursor positioning with wide characters.
     */
    public function testMultiLineCursorUsesDisplayWidth()
    {
        $this->renderer->setSingleLinePrompt('>>> ');
        $this->renderer->setMultilinePrompt('... ');

        $buffer = new Buffer();
        // Line 0: "if (true) {"
        // Line 1: "  中文"  (2 spaces + 2 wide chars = 6 display columns)
        $this->setBufferState($buffer, "if (true) {\n  中文<cursor>");

        $this->renderer->render($buffer, true, null);

        // Prompt "... " = 4 display columns
        // "  中文" = 2 + 4 = 6 display columns
        // Cursor column = 4 + 6 + 1 = 11
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(11, $lastColumn);
    }

    /**
     * Sanity check: ASCII-only text should work correctly too.
     */
    public function testSingleLineCursorWithAsciiOnly()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello<cursor>');

        $this->renderer->render($buffer, false, null);

        // Prompt = 4, "hello" = 5
        // Cursor column = 4 + 5 + 1 = 10
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(10, $lastColumn);
    }

    public function testCursorColumnWrapsAtTerminalWidth()
    {
        $this->renderer->setSingleLinePrompt('>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, \str_repeat('a', 77).'<cursor>');

        $this->renderer->render($buffer, false, null);

        // Prompt (3) + text (77) => absolute column 81, wrapped to col 1.
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(1, $lastColumn);
    }

    public function testWrappedInputConsumesOverlayViewportRows()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, \str_repeat('a', 200).'<cursor>');

        $this->renderer->render($buffer, false, null);

        // Prompt (4) + text (200) => 204 columns => 3 wrapped rows.
        // Available = 24 - 3 (input) - 1 breathing room = 20.
        $this->assertSame(20, $this->viewport->getAvailableRows(false));
    }

    public function testWrappedInputCountsLiteralFormatterLikeTags()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, \str_repeat('<info>x</info>', 6).'<cursor>');

        $this->renderer->render($buffer, false, null);

        // Prompt (4) + literal text (84) => 88 columns => 2 wrapped rows.
        // Available = 24 - 2 (input) - 1 breathing room = 21.
        $this->assertSame(21, $this->viewport->getAvailableRows(false));
    }

    public function testRendererMovesBackByWrappedCursorRowBeforeRepaint()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, \str_repeat('a', 170).'<cursor>');
        $this->renderer->render($buffer, false, null);

        $this->cursorUpMoves = [];
        $this->setBufferState($buffer, 'ok<cursor>');
        $this->renderer->render($buffer, false, null);

        // First render cursor row: floor((4 + 170) / 80) = 2.
        $this->assertSame([2], $this->cursorUpMoves);
    }

    public function testSuggestionIsHiddenWhenOverlayIsVisible()
    {
        $this->renderer->setSingleLinePrompt('>>> ');
        $this->renderer->setOverlayLines(['   menu item']);

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'pri<cursor>');
        $suggestion = new SuggestionResult('nt("hello")', 'history', 'print("hello")');

        $this->renderer->render($buffer, false, $suggestion);

        $this->assertStringNotContainsString('nt("hello")', \implode('', $this->writes));
    }

    public function testCursorOnlyMovementDoesNotRepaintFrame()
    {
        $this->renderer->setSingleLinePrompt('>>> ');

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'abcdef<cursor>');
        $this->renderer->render($buffer, false, null);

        $this->clearToEndOfScreenCalls = 0;
        $this->writes = [];

        $this->setBufferState($buffer, 'ab<cursor>cdef');
        $this->renderer->render($buffer, false, null);

        $this->assertSame(0, $this->clearToEndOfScreenCalls);
        $this->assertSame([], $this->writes);
    }

    public function testRepaintStartsAtFirstChangedLogicalLine()
    {
        $this->renderer->setSingleLinePrompt('>>> ');
        $this->renderer->setOverlayLines([
            '   one',
            '   two',
            '   three',
        ]);

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'x<cursor>');
        $this->renderer->render($buffer, false, null);

        $this->cursorDownMoves = [];
        $this->cursorUpMoves = [];
        $this->clearToEndOfScreenCalls = 0;

        $this->renderer->setOverlayLines([
            '   one',
            '   TWO',
            '   three',
        ]);
        $this->renderer->render($buffer, false, null);

        // Changed line index is 2 (input + first overlay unchanged), so seek to row 2.
        $this->assertSame([2], $this->cursorDownMoves);
        $this->assertSame(1, $this->clearToEndOfScreenCalls);
    }
}
