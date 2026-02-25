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
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class FrameRendererTest extends TestCase
{
    use BufferAssertionTrait;
    private Terminal $terminal;
    private FrameRenderer $renderer;

    /** @var int[] */
    private array $cursorColumns = [];

    protected function setUp(): void
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }

        $this->terminal = $this->createMock(Terminal::class);
        $viewport = new OverlayViewport($this->terminal);
        $this->renderer = new FrameRenderer($this->terminal, $viewport);

        $this->cursorColumns = [];

        $this->terminal->method('moveCursorToColumn')
            ->willReturnCallback(function (int $col) {
                $this->cursorColumns[] = $col;
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

        $this->renderer->render($buffer, false);

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

        $this->renderer->render($buffer, false);

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

        $this->renderer->render($buffer, true);

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

        $this->renderer->render($buffer, false);

        // Prompt = 4, "hello" = 5
        // Cursor column = 4 + 5 + 1 = 10
        $lastColumn = \end($this->cursorColumns);
        $this->assertSame(10, $lastColumn);
    }
}
