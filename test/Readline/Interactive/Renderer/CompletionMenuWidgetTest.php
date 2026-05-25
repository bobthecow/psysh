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
use Psy\Readline\Interactive\Renderer\CompletionMenuWidget;
use Psy\Readline\Interactive\Renderer\Frame;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class CompletionMenuWidgetTest extends TestCase
{
    /**
     * @dataProvider layoutProvider
     */
    public function testCalculateLayout(int $termWidth, array $items, int $expectedColumns)
    {
        $terminal = $this->getTerminal($termWidth);
        $layout = CompletionMenuWidget::calculateLayout($terminal, $items);

        $this->assertSame($expectedColumns, $layout['columns']);
    }

    /**
     * @return array[]
     */
    public function layoutProvider(): array
    {
        return [
            'fits in multiple columns' => [
                80,
                ['foo', 'bar', 'baz', 'qux'],
                16,
            ],
            'wide item forces single column' => [
                40,
                ['short', \str_repeat('x', 50)],
                1,
            ],
        ];
    }

    public function testSingleColumnWidthCappedToTerminalWidth()
    {
        $terminal = $this->getTerminal(40);
        $items = [\str_repeat('x', 80), 'short'];
        $layout = CompletionMenuWidget::calculateLayout($terminal, $items);

        $this->assertSame(1, $layout['columns']);
        // 40 terminal width - 3 indent = 37 max column width
        $this->assertSame(37, $layout['columnWidths'][0]);
    }

    public function testWideItemsTruncatedInOutput()
    {
        $terminal = $this->getTerminal(40);

        $wideItem = \str_repeat('x', 80);
        $lines = $this->renderMenu($terminal, [$wideItem, 'short']);

        // Each rendered line should fit within terminal width
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(40, \mb_strlen($line));
        }
    }

    public function testWideItemShowsEllipsis()
    {
        $terminal = $this->getTerminal(40);

        $wideItem = \str_repeat('abcdef', 20);
        $output = \implode("\n", $this->renderMenu($terminal, [$wideItem]));

        $this->assertStringContainsString('...', $output);
    }

    public function testNewlinesCollapsedForDisplay()
    {
        $terminal = $this->getTerminal(80);

        $output = \implode("\n", $this->renderMenu($terminal, ["foreach (\$x as \$y) {\n    echo \$y;\n}"]));

        $this->assertStringContainsString('foreach ($x as $y) { echo $y; }', $output);
        $this->assertStringNotContainsString("\n    ", $output);
    }

    public function testNewlinesCollapsedInLayoutCalculation()
    {
        $terminal = $this->getTerminal(80);

        // A multi-line item that's short when collapsed
        $layout = CompletionMenuWidget::calculateLayout($terminal, ["foo\nbar", 'baz']);

        // "foo bar" is 7 chars, should fit in many columns
        $this->assertGreaterThan(1, $layout['columns']);
    }

    public function testNormalItemsNotTruncated()
    {
        $terminal = $this->getTerminal(80);

        $output = \implode("\n", $this->renderMenu($terminal, ['foo', 'bar', 'baz']));

        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
        $this->assertStringContainsString('baz', $output);
        $this->assertStringNotContainsString('...', $output);
    }

    public function testRenderPrependsBlankSeparatorLine()
    {
        $terminal = $this->getTerminal(80);
        $lines = $this->renderMenu($terminal, ['foo']);

        $this->assertSame('', $lines[0]);
    }

    public function testEmptyItemsRendersNoMatches()
    {
        $terminal = $this->getTerminal(80);
        $lines = $this->renderMenu($terminal, []);

        $this->assertCount(2, $lines);
        $this->assertSame('', $lines[0]);
        $this->assertStringContainsString('(no matches)', $lines[1]);
    }

    public function testTruncatedStatusUsesAsciiWhenUnicodeIsDisabled(): void
    {
        $terminal = $this->getTerminal(80);
        $terminal->method('useUnicode')->willReturn(false);

        $items = \array_map(static fn (int $i) => \str_repeat('item'.$i, 8), \range(1, 12));
        $widget = new CompletionMenuWidget($terminal, $items, -1, 0, false);
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area(80, 3));

        $output = \implode("\n", $frame->getLines());
        $this->assertStringContainsString('...and', $output);
        $this->assertStringNotContainsString('…', $output);
    }

    /**
     * Render a menu with default state (no selection, expanded so the
     * compact half-cap doesn't constrain results), and return the lines
     * the widget appended.
     *
     * @param string[] $items
     *
     * @return string[]
     */
    private function renderMenu(Terminal $terminal, array $items): array
    {
        $widget = new CompletionMenuWidget($terminal, $items, -1, 0, true);
        $frame = new Frame([], 0, 0);
        $widget->render($frame, new Area($terminal->getWidth(), 100));

        return $frame->getLines();
    }

    private function getTerminal(int $width): MockObject
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getWidth')->willReturn($width);
        $terminal->method('getHeight')->willReturn(50);
        $terminal->method('format')->willReturnCallback(
            static fn (string $text) => \strip_tags($text),
        );

        return $terminal;
    }
}
