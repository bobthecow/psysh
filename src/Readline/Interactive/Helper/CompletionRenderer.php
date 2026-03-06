<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Helper;

use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Readline\Interactive\Terminal;

/**
 * Renders tab completion menu in compact columns.
 *
 * Fast manual renderer optimized for interactive completion menus.
 */
class CompletionRenderer
{
    private Terminal $terminal;

    public function __construct(Terminal $terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * Render completion items in a compact columnar menu.
     *
     * @param string[] $items         Completion matches
     * @param int      $selectedIndex Index of selected item (-1 for none)
     * @param int|null $maxRows       Maximum visible rows (null for unlimited)
     * @param int      $scrollOffset  First visible row index
     *
     * @return string[] The rendered lines
     */
    public function render(
        array $items,
        int $selectedIndex = -1,
        ?int $maxRows = null,
        int $scrollOffset = 0,
        bool $compact = true
    ): array {
        if (empty($items)) {
            return [$this->terminal->format(
                '  <whisper>(no matches)</whisper>',
            )];
        }

        $items = \array_map([History::class, 'collapseToSingleLine'], \array_values($items));
        $layout = $this->doCalculateLayout($items);
        $totalRows = $layout['rows'];
        $columns = $layout['columns'];
        $columnWidths = $layout['columnWidths'];
        $count = \count($items);

        // Determine visible row range
        $needsTruncation = $maxRows !== null && $totalRows > $maxRows + 1;
        $startRow = $needsTruncation ? $scrollOffset : 0;
        $endRow = $needsTruncation
            ? \min($totalRows, $startRow + $maxRows)
            : $totalRows;

        $lines = [];
        $hasSelection = $selectedIndex >= 0;

        $formatter = $this->terminal->getFormatter();
        $highlightStyle = $formatter->isDecorated() && $formatter->hasStyle('input_highlight') ? $formatter->getStyle('input_highlight') : null;

        for ($row = $startRow; $row < $endRow; $row++) {
            $line = '   ';
            for ($col = 0; $col < $columns; $col++) {
                $index = $row + $col * $totalRows;
                if ($index < $count) {
                    $colWidth = $columnWidths[$col];
                    $item = DisplayString::truncate($items[$index], $colWidth, true);
                    $itemWidth = DisplayString::width($item);
                    $padding = \max(0, $colWidth - $itemWidth);

                    if ($hasSelection && $index === $selectedIndex) {
                        $highlighted = $item.\str_repeat(' ', $padding);
                        $line .= $highlightStyle ? $highlightStyle->apply($highlighted) : $highlighted;
                    } else {
                        $line .= $item.\str_repeat(' ', $padding);
                    }

                    if ($col < $columns - 1) {
                        $line .= '  ';
                    }
                }
            }
            $lines[] = $line;
        }

        if ($needsTruncation) {
            $lines[] = $this->renderStatusLine(
                $startRow,
                $endRow,
                $totalRows,
                $compact,
            );
        }

        return $lines;
    }

    /**
     * Calculate the column layout for a set of items.
     *
     * @param string[] $items Completion matches
     *
     * @return array{rows: int, columns: int, columnWidths: int[]}
     */
    public function calculateLayout(array $items): array
    {
        return $this->doCalculateLayout(\array_map([History::class, 'collapseToSingleLine'], $items));
    }

    /**
     * @param string[] $items Display-ready (single-line) items
     *
     * @return array{rows: int, columns: int, columnWidths: int[]}
     */
    private function doCalculateLayout(array $items): array
    {
        $widths = \array_map([DisplayString::class, 'width'], $items);
        $count = \count($items);
        $maxWidth = $this->terminal->getWidth();

        // Start with a naive guess based on the widest item
        $columns = \max(1, \intdiv($maxWidth, \max($widths) + 2));
        $columnWidths = $this->calculateColumnWidths($widths, $count, $columns);
        $maxColumns = \min($count, $columns + 5);

        // Check up to five more columns, to see if there's a more optimal
        // layout after wrapping.
        for ($try = $columns + 1; $try <= $maxColumns; $try++) {
            $candidate = $this->calculateColumnWidths($widths, $count, $try);
            if (\array_sum($candidate) + ($try - 1) * 2 + 3 <= $maxWidth) {
                $columns = $try;
                $columnWidths = $candidate;
            }
        }

        // Cap single-column width so wide items don't soft-wrap.
        // Multi-column layouts are already validated by the loop above.
        if ($columns === 1) {
            $columnWidths[0] = \min($columnWidths[0], $maxWidth - 3);
        }

        return [
            'rows'         => $this->calculateRowCount($count, $columns),
            'columns'      => $columns,
            'columnWidths' => $columnWidths,
        ];
    }

    /**
     * Render the status line for truncated menus.
     */
    private function renderStatusLine(
        int $startRow,
        int $endRow,
        int $totalRows,
        bool $compact
    ): string {
        if ($compact && $startRow === 0) {
            $remaining = $totalRows - $endRow;
            $text = \sprintf('…and %d more rows', $remaining);
        } else {
            $text = \sprintf(
                'rows %d to %d of %d',
                $startRow + 1,
                $endRow,
                $totalRows,
            );
        }

        return $this->terminal->format('   <whisper>'.$text.'</whisper>');
    }

    /**
     * Calculate per-column widths for a given column count.
     *
     * @param int[] $widths  Pre-computed item widths
     * @param int   $count   Total item count
     * @param int   $columns Number of columns
     *
     * @return int[] Width of each column
     */
    private function calculateColumnWidths(
        array $widths,
        int $count,
        int $columns
    ): array {
        $rows = $this->calculateRowCount($count, $columns);
        $columnWidths = \array_fill(0, $columns, 0);
        for ($i = 0; $i < $count; $i++) {
            $col = \intdiv($i, $rows);
            $columnWidths[$col] = \max($columnWidths[$col], $widths[$i]);
        }

        return $columnWidths;
    }

    /**
     * Calculate the number of rows needed for a column-first layout.
     */
    private function calculateRowCount(int $itemCount, int $columns): int
    {
        return \intdiv($itemCount + $columns - 1, $columns);
    }
}
