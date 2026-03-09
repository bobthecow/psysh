<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Helper;

use Psy\Readline\Interactive\Helper\CompletionRenderer;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class CompletionRendererTest extends TestCase
{
    /**
     * @dataProvider layoutProvider
     */
    public function testCalculateLayout(int $termWidth, array $items, int $expectedColumns)
    {
        $terminal = $this->getTerminal($termWidth);
        $renderer = new CompletionRenderer($terminal);
        $layout = $renderer->calculateLayout($items);

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
        $renderer = new CompletionRenderer($terminal);
        $items = [\str_repeat('x', 80), 'short'];
        $layout = $renderer->calculateLayout($items);

        $this->assertSame(1, $layout['columns']);
        // 40 terminal width - 3 indent = 37 max column width
        $this->assertSame(37, $layout['columnWidths'][0]);
    }

    public function testWideItemsTruncatedInOutput()
    {
        $terminal = $this->getTerminal(40);
        $renderer = new CompletionRenderer($terminal);

        $wideItem = \str_repeat('x', 80);
        $lines = $renderer->render([$wideItem, 'short']);

        // Each rendered line should fit within terminal width
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(40, \mb_strlen($line));
        }
    }

    public function testWideItemShowsEllipsis()
    {
        $terminal = $this->getTerminal(40);
        $renderer = new CompletionRenderer($terminal);

        $wideItem = \str_repeat('abcdef', 20);
        $output = \implode("\n", $renderer->render([$wideItem]));

        $this->assertStringContainsString('...', $output);
    }

    public function testNewlinesCollapsedForDisplay()
    {
        $terminal = $this->getTerminal(80);
        $renderer = new CompletionRenderer($terminal);

        $output = \implode("\n", $renderer->render(["foreach (\$x as \$y) {\n    echo \$y;\n}"]));

        $this->assertStringContainsString('foreach ($x as $y) { echo $y; }', $output);
        $this->assertStringNotContainsString("\n    ", $output);
    }

    public function testNewlinesCollapsedInLayoutCalculation()
    {
        $terminal = $this->getTerminal(80);
        $renderer = new CompletionRenderer($terminal);

        // A multi-line item that's short when collapsed
        $layout = $renderer->calculateLayout(["foo\nbar", 'baz']);

        // "foo bar" is 7 chars, should fit in many columns
        $this->assertGreaterThan(1, $layout['columns']);
    }

    public function testNormalItemsNotTruncated()
    {
        $terminal = $this->getTerminal(80);
        $renderer = new CompletionRenderer($terminal);

        $output = \implode("\n", $renderer->render(['foo', 'bar', 'baz']));

        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
        $this->assertStringContainsString('baz', $output);
        $this->assertStringNotContainsString('...', $output);
    }

    private function getTerminal(int $width): Terminal
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getWidth')->willReturn($width);
        $terminal->method('format')->willReturnCallback(
            static fn (string $text) => \strip_tags($text),
        );

        return $terminal;
    }
}
