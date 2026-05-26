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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\Renderer\Area;
use Psy\Readline\Interactive\Renderer\Frame;
use Psy\Readline\Interactive\Renderer\LineMetrics;
use Psy\Readline\Interactive\Renderer\PagerWidget;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class PagerWidgetTest extends TestCase
{
    public function testRendersViewportSliceFromScrollOffset(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            ['one', 'two', 'three', 'four', 'five'],
            1,
        );

        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 4)); // 3 viewport rows + 1 status

        $lines = $frame->getLines();
        $this->assertSame('two', $lines[0]);
        $this->assertSame('three', $lines[1]);
        $this->assertSame('four', $lines[2]);
        $this->assertStringContainsString('4/5', $lines[3]);
    }

    public function testStatusLineShowsPositionAndPercentage(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            \array_fill(0, 100, 'line'),
            0,
        );

        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 11));

        $status = $frame->getLines()[10];
        $this->assertStringContainsString('10/100', $status);
        $this->assertStringContainsString('10%', $status);
    }

    public function testStatusLineShowsDefaultHintWhenNotSearching(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget($terminal, new LineMetrics($terminal), ['a', 'b']);
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 3));

        $this->assertStringContainsString('j/k', $frame->getLines()[2]);
        $this->assertStringContainsString('search', $frame->getLines()[2]);
        $this->assertStringContainsString('quit', $frame->getLines()[2]);
    }

    public function testStatusLineShowsSearchInputWhenActive(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            ['foo', 'bar'],
            0,
            'fo',
            true,
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 3));

        $this->assertStringContainsString('search:', $frame->getLines()[2]);
        $this->assertStringContainsString('fo', $frame->getLines()[2]);
    }

    public function testStatusLineShowsMatchCountWhenSearching(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            ['foo', 'bar', 'foobar'],
            0,
            'foo',
            false,
            null,
            2,
            0,
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 4));

        $status = $frame->getLines()[3];
        $this->assertStringContainsString('/foo', $status);
        $this->assertStringContainsString('1/2 matches', $status);
    }

    public function testStatusLineShowsNoMatchesWhenSearchHasNone(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            ['foo', 'bar'],
            0,
            'xyz',
            false,
            null,
            0,
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 3));

        $this->assertStringContainsString('(no matches)', $frame->getLines()[2]);
    }

    public function testStatusLineUsesAsciiWhenUnicodeIsDisabled(): void
    {
        $terminal = $this->getTerminal(80);
        $terminal->method('useUnicode')->willReturn(false);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            \array_fill(0, 100, 'line'),
            0,
            'needle',
            false,
            null,
            2,
            0,
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 4));

        $status = $frame->getLines()[3];
        $this->assertStringContainsString(' | ', $status);
        $this->assertStringNotContainsString('·', $status);
        $this->assertStringNotContainsString("\u{258D}", $status);
    }

    public function testOversizedLineRendersTruncatedPreview(): void
    {
        // Width 40, line that wraps to 5 rows (200 chars). With 4 viewport rows
        // available, the widget should still render visible content, truncated
        // to the viewport budget so the status line stays anchored.
        $terminal = $this->getTerminal(40);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            [\str_repeat('x', 200), 'short'],
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(40, 5)); // 4 viewport + 1 status

        $lines = $frame->getLines();
        $this->assertSame(\str_repeat('x', 157).'...', $lines[0]);
        $this->assertStringContainsString('1/2', $lines[1]);
    }

    public function testWrappingLineIsEmittedWhenBudgetFits(): void
    {
        // Width 40, viewport 5 rows. A 3-row wrapped line plus a normal line fits.
        $terminal = $this->getTerminal(40);
        $widget = new PagerWidget(
            $terminal,
            new LineMetrics($terminal),
            [\str_repeat('x', 110), 'short'],
        );
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(40, 6)); // 5 viewport + 1 status

        $lines = $frame->getLines();
        $this->assertSame(\str_repeat('x', 110), $lines[0]);
        $this->assertSame('short', $lines[1]);
        // Wide line consumed 3 rows + short line 1 row = 4 rows; one blank pad
        // before the status. Frame contains one entry per logical line + status.
        $this->assertSame('', $lines[2]);
        $this->assertStringContainsString('2/2', $lines[3]);
    }

    public function testEmptyContentRendersStatusOnly(): void
    {
        $terminal = $this->getTerminal(80);
        $widget = new PagerWidget($terminal, new LineMetrics($terminal), []);
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 3));

        $lines = $frame->getLines();
        $this->assertSame('', $lines[0]);
        $this->assertSame('', $lines[1]);
        $this->assertStringContainsString('0/0', $lines[2]);
    }

    private function getTerminal(int $width): MockObject
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getWidth')->willReturn($width);
        $terminal->method('getHeight')->willReturn(24);
        $terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $terminal->method('format')->willReturnCallback(
            static fn (string $text) => \strip_tags($text),
        );

        return $terminal;
    }
}
