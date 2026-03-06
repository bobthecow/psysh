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
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Renders history search results in a single-column list.
 *
 * Each item is prefixed with a prompt and the selected item is highlighted.
 */
class HistorySearchRenderer
{
    private Terminal $terminal;
    private string $prefix;
    private string $query = '';

    public function __construct(Terminal $terminal, string $prefix = '- ')
    {
        $this->terminal = $terminal;
        $this->prefix = $prefix;
    }

    /**
     * Set the search query for highlighting matches.
     */
    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * Render history search items in a single-column list.
     *
     * @param string[] $items         History matches (newest first)
     * @param int      $selectedIndex Index of selected item (-1 for none)
     * @param int|null $maxRows       Maximum visible rows (null for unlimited)
     * @param int      $scrollOffset  First visible row index
     * @param int      $totalCount    Total match count for status line
     *
     * @return string[] The rendered lines
     */
    public function render(
        array $items,
        int $selectedIndex = 0,
        ?int $maxRows = null,
        int $scrollOffset = 0,
        int $totalCount = 0
    ): array {
        if (empty($items)) {
            return [$this->terminal->format('  <whisper>(no matches)</whisper>')];
        }

        $items = \array_map([History::class, 'collapseToSingleLine'], \array_values($items));
        $prefixWidth = DisplayString::width($this->prefix);
        $indent = \str_repeat(' ', $prefixWidth);
        $maxWidth = \max(1, $this->terminal->getWidth() - $prefixWidth);
        $totalRows = \count($items);

        $needsTruncation = $maxRows !== null && $totalRows > $maxRows + 1;
        $startRow = $needsTruncation ? $scrollOffset : 0;
        $endRow = $needsTruncation
            ? \min($totalRows, $startRow + $maxRows)
            : $totalRows;

        $lines = [];

        for ($row = $startRow; $row < $endRow; $row++) {
            $item = DisplayString::truncate($items[$row], $maxWidth, true);

            if ($row === $selectedIndex) {
                $escaped = OutputFormatter::escape($item);
                $itemWidth = DisplayString::width($item);
                $padding = \max(0, $maxWidth - $itemWidth);
                $lines[] = $this->terminal->format(
                    '<input_highlight>'.$this->prefix.$escaped.\str_repeat(' ', $padding).'</input_highlight>',
                );
            } else {
                $lines[] = $indent.$this->highlightQuery($item);
            }
        }

        if ($needsTruncation) {
            $text = \sprintf('Items %d to %d of %d', $startRow + 1, $endRow, $totalCount > 0 ? $totalCount : $totalRows);
            $lines[] = $this->terminal->format($indent.'<whisper>'.$text.'</whisper>');
        }

        return $lines;
    }

    /**
     * Highlight the first occurrence of the search query in an item.
     */
    private function highlightQuery(string $item): string
    {
        if ($this->query === '') {
            return $item;
        }

        $caseSensitive = History::isSearchCaseSensitive($this->query);
        $pos = $caseSensitive
            ? \mb_strpos($item, $this->query)
            : \mb_stripos($item, $this->query);

        if ($pos === false) {
            return $item;
        }

        $before = \mb_substr($item, 0, $pos);
        $match = \mb_substr($item, $pos, \mb_strlen($this->query));
        $after = \mb_substr($item, $pos + \mb_strlen($this->query));

        return $before.$this->terminal->format('<info>'.OutputFormatter::escape($match).'</info>').$after;
    }
}
