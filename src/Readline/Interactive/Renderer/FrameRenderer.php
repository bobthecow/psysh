<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Renderer;

use Psy\Formatter\CodeFormatter;
use Psy\Output\Theme;
use Psy\Readline\Interactive\Helper\CommandHighlighter;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Readline\Interactive\Layout\SoftWrapCalculator;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * Frame-buffered renderer for interactive readline.
 *
 * Builds the visible area (input + overlay) as logical lines and lets the
 * terminal perform soft wrapping. The renderer tracks wrapped row occupancy
 * itself so cursor positioning and overlay budgeting remain correct.
 */
class FrameRenderer
{
    private const CLEAR_TO_END_OF_LINE = "\033[K";
    private const INPUT_FRAME_PADDING_ROWS = 2;

    private Terminal $terminal;
    private OverlayViewport $viewport;
    private Theme $theme;
    private CommandHighlighter $commandHighlighter;

    /** @var string[] Lines currently displayed on the terminal. */
    private array $previousFrame = [];

    /** Which wrapped frame row the terminal cursor is currently on (0-indexed). */
    private int $cursorFrameRow = 0;

    /** @var string[] Overlay lines to display below the input. */
    private array $overlayLines = [];

    /** @var string[] Previously submitted lines rendered above current input within the frame. */
    private array $historyLines = [];

    /** Cached wrapped row count for history lines, invalidated on change. */
    private int $historyRowCount = 0;

    private bool $errorMode = false;
    private bool $useSyntaxHighlighting = true;

    private ?int $lastTerminalWidth = null;
    private ?int $lastTerminalHeight = null;
    private ?SoftWrapCalculator $softWrapCalculator = null;
    private ?int $softWrapCachedWidth = null;

    /** @var array<string, int> Cached row counts keyed by line content. */
    private array $lineRowCache = [];

    /** @var array<string, int> Cached prompt widths keyed by "line:decorated". */
    private array $promptWidthCache = [];

    public function __construct(Terminal $terminal, OverlayViewport $viewport, ?Theme $theme = null)
    {
        $this->terminal = $terminal;
        $this->viewport = $viewport;
        $this->theme = $theme ?? new Theme();
        $this->commandHighlighter = new CommandHighlighter();
    }

    /**
     * Get the command highlighter for CommandAware registration.
     */
    public function getCommandHighlighter(): CommandHighlighter
    {
        return $this->commandHighlighter;
    }

    /**
     * Set the theme for prompt strings and compact mode.
     */
    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
        $this->promptWidthCache = [];
    }

    /**
     * Enable or disable syntax highlighting for input rendering.
     */
    public function setUseSyntaxHighlighting(bool $enabled): void
    {
        $this->useSyntaxHighlighting = $enabled;
    }

    /**
     * Check whether compact input frame rendering is enabled.
     */
    public function isCompactInputFrame(): bool
    {
        return $this->theme->compact();
    }

    /**
     * Set the error mode for the input frame.
     */
    public function setErrorMode(bool $error): void
    {
        $this->errorMode = $error;
    }

    /**
     * Get number of outer rows surrounding input content.
     */
    public function getInputFrameOuterRowCount(): int
    {
        return $this->theme->compact() ? 0 : self::INPUT_FRAME_PADDING_ROWS;
    }

    /**
     * Get active prompt display width for the cursor's current line.
     */
    public function getPromptWidthForCurrentLine(Buffer $buffer): int
    {
        $lineNumber = 0;
        if (\strpos($buffer->getText(), "\n") !== false) {
            $lineNumber = $buffer->getCurrentLineNumber();
        }

        return $this->getPromptWidthForLine($lineNumber, $this->terminal->getFormatter());
    }

    /**
     * Get the prompt string for a given line number.
     */
    private function getPromptForLine(int $lineNumber): string
    {
        return $lineNumber === 0 ? $this->theme->prompt() : $this->theme->bufferPrompt();
    }

    /**
     * Get the display width of the prompt for a given line number.
     */
    private function getPromptWidthForLine(int $lineNumber, ?OutputFormatterInterface $formatter = null): int
    {
        $key = ($lineNumber === 0 ? '0' : '1').($formatter !== null ? ':f' : ':p');
        if (isset($this->promptWidthCache[$key])) {
            return $this->promptWidthCache[$key];
        }

        $prompt = $this->getPromptForLine($lineNumber);
        $width = ($formatter === null)
            ? DisplayString::width($prompt)
            : DisplayString::widthWithoutFormatting($prompt, $formatter);

        $this->promptWidthCache[$key] = $width;

        return $width;
    }

    /**
     * Add a previously submitted input to the in-frame history.
     */
    public function addHistoryLines(string $text, bool $isCommand = false): void
    {
        $newLines = $this->formatHighlightedLinesWithPrompts($this->formatInputLines($text, null, $isCommand));
        \array_push($this->historyLines, ...$newLines);

        foreach ($newLines as $line) {
            $this->historyRowCount += $this->lineRowCount($line);
        }
    }

    /**
     * Clear previously submitted lines rendered above the current input.
     */
    public function clearHistoryLines(): void
    {
        $this->historyLines = [];
        $this->historyRowCount = 0;
    }

    /**
     * Set overlay lines to display below the input.
     *
     * @param string[] $lines
     */
    public function setOverlayLines(array $lines): void
    {
        $this->overlayLines = $lines;
    }

    /**
     * Clear the overlay and re-render.
     */
    public function clearOverlay(Buffer $buffer): void
    {
        $this->overlayLines = [];
        $this->render($buffer, null);
    }

    /**
     * Render the full frame (input + overlay) to the terminal.
     */
    public function render(Buffer $buffer, ?SuggestionResult $suggestion, ?string $historySearchTerm = null, bool $isCommand = false): void
    {
        $isMultiline = \strpos($buffer->getText(), "\n") !== false;
        $inputLines = $this->buildInputLines($buffer, $isMultiline, $suggestion, $historySearchTerm, $isCommand);

        $this->viewport->setInputRowCount($this->getFrameRowCount($inputLines));

        $frame = \array_merge($inputLines, $this->overlayLines);

        [$cursorRow, $cursorColumn] = $this->getCursorPosition($buffer, $isMultiline);

        $this->syncFrame($frame, $cursorRow, $cursorColumn);
        $this->terminal->flush();
    }

    /**
     * Render the search UI: preview in the input frame, search field + results below.
     *
     * @param string $previewText  Text to show in the input frame as a preview
     * @param string $searchPrompt The search field line (cursor goes here)
     */
    public function renderSearchFrame(string $previewText, string $searchPrompt): void
    {
        // Show a concise, truncated preview on the prompt line.
        $promptLine = $this->getPromptForLine(0);
        if ($previewText !== '') {
            $collapsed = History::collapseToSingleLine($previewText);
            $promptWidth = $this->getPromptWidthForLine(0, $this->terminal->getFormatter());
            $maxWidth = $this->getTerminalWidth() - $promptWidth;
            $truncated = DisplayString::truncate($collapsed, $maxWidth, true);
            $promptLine .= OutputFormatter::escape($truncated);
        }

        $inputLines = $this->wrapInInputFrame([$promptLine]);

        $inputRowCount = $this->getFrameRowCount($inputLines);
        $searchPromptRows = $this->lineRowCount($searchPrompt);
        $this->viewport->setInputRowCount($inputRowCount + $searchPromptRows);

        // Frame = input frame + search field + overlay results
        $frame = \array_merge($inputLines, [$searchPrompt], $this->overlayLines);

        // Position cursor at end of search prompt, below the input frame
        $calculator = $this->getSoftWrapCalculator();
        $absoluteColumn = DisplayString::widthWithoutAnsi($searchPrompt) + 1;
        $searchLineRow = $this->getRowOffsetBeforeLine($frame, \count($inputLines));
        $cursorRow = $searchLineRow + $calculator->rowOffsetForAbsoluteColumn($absoluteColumn);
        $cursorColumn = $calculator->normalizeColumn($absoluteColumn);

        $this->syncFrame($frame, $cursorRow, $cursorColumn);
        $this->terminal->flush();
    }

    /**
     * Reset renderer state (call when starting a new readline session).
     */
    public function reset(): void
    {
        $this->previousFrame = [];
        $this->cursorFrameRow = 0;
        $this->overlayLines = [];
        $this->historyLines = [];
        $this->historyRowCount = 0;
        $this->errorMode = false;
        $this->lastTerminalWidth = null;
        $this->lastTerminalHeight = null;
        $this->lineRowCache = [];
    }

    /**
     * Build the input section of the frame.
     *
     * @return string[]
     */
    private function buildInputLines(Buffer $buffer, bool $isMultiline, ?SuggestionResult $suggestion, ?string $historySearchTerm = null, bool $isCommand = false): array
    {
        $text = $buffer->getText();
        $contentLines = [];
        if ($isMultiline) {
            $contentLines = $this->formatHighlightedLinesWithPrompts($this->formatInputLines($text, $historySearchTerm, $isCommand));
        } else {
            $line = $this->getPromptForLine(0).\implode("\n", $this->formatInputLines($text, $historySearchTerm, $isCommand));

            if ($suggestion !== null) {
                $line = $this->appendSuggestionGhostText($line, $buffer, $text, $suggestion);
            }

            $contentLines[] = $line;
        }

        return $this->wrapInInputFrame($contentLines);
    }

    /**
     * Wrap content lines in the input frame (background, padding).
     *
     * @param string[] $contentLines
     *
     * @return string[]
     */
    private function wrapInInputFrame(array $contentLines): array
    {
        if ($this->theme->compact()) {
            return \array_merge($this->historyLines, $contentLines);
        }

        $styleName = $this->errorMode ? 'input_frame_error' : 'input_frame';
        $formatter = $this->terminal->getFormatter();
        $inputFrameStyle = ($formatter->isDecorated() && $formatter->hasStyle($styleName))
            ? $formatter->getStyle($styleName)
            : null;

        $framedLines = [''];
        foreach (['', ...$this->historyLines, ...$contentLines, ''] as $line) {
            $lineWithClear = $line.self::CLEAR_TO_END_OF_LINE;
            $framedLines[] = $inputFrameStyle ? $inputFrameStyle->apply($lineWithClear) : $lineWithClear;
        }
        $framedLines[] = '';

        return $framedLines;
    }

    /**
     * Format raw input text into prompt-prefixed lines.
     *
     * @return string[]
     */
    private function formatHighlightedLinesWithPrompts(array $lines): array
    {
        $result = [];
        foreach ($lines as $i => $line) {
            $result[] = $this->getPromptForLine($i).$line;
        }

        return $result;
    }

    /**
     * Highlight all occurrences of a search term in text.
     *
     * Uses smart case (case-insensitive unless the term contains uppercase).
     */
    private function highlightSearchTerm(string $text, string $term): string
    {
        $formatter = $this->terminal->getFormatter();
        if (!$formatter->isDecorated() || !$formatter->hasStyle('input_highlight')) {
            return $text;
        }

        $style = $formatter->getStyle('input_highlight');
        $pattern = '/'.\preg_quote($term, '/').'/u'.(History::isSearchCaseSensitive($term) ? '' : 'i');
        $highlighted = \preg_replace_callback($pattern, fn (array $match) => $style->apply($match[0]), $text);

        return $highlighted ?? $text;
    }

    /**
     * Append single-line suggestion ghost text when there is room.
     */
    private function appendSuggestionGhostText(string $line, Buffer $buffer, string $text, SuggestionResult $suggestion): string
    {
        // Completion overlays own the viewport; don't mix ghost text with menus.
        if (!empty($this->overlayLines)) {
            return $line;
        }

        $absoluteCursorColumn = $this->getPromptWidthForLine(0, $this->terminal->getFormatter())
            + DisplayString::width(\mb_substr($text, 0, $buffer->getCursor())) + 1;
        $cursorColumn = $this->getSoftWrapCalculator()->normalizeColumn($absoluteCursorColumn);
        $maxWidth = $this->getTerminalWidth() - $cursorColumn + 1;
        if ($maxWidth <= 0) {
            return $line;
        }

        $suggestionText = DisplayString::truncate($suggestion->getDisplayText(), $maxWidth, true);
        if ($suggestionText === '') {
            return $line;
        }

        return $line.$this->terminal->format('<whisper>'.OutputFormatter::escape($suggestionText).'</whisper>');
    }

    /**
     * Get the wrapped frame row and terminal column where the cursor should be.
     *
     * @return array{int, int}
     */
    private function getCursorPosition(Buffer $buffer, bool $isMultiline): array
    {
        $text = $buffer->getText();
        $historyRowOffset = $this->historyRowCount;

        if ($isMultiline) {
            $lines = \explode("\n", $text);
            $lineNum = $buffer->getCurrentLineNumber();
            $promptWidth = $this->getPromptWidthForLine($lineNum, $this->terminal->getFormatter());

            $charsBeforeLine = 0;
            $rowsBeforeLine = 0;
            for ($i = 0; $i < $lineNum; $i++) {
                $charsBeforeLine += \mb_strlen($lines[$i]) + 1;
                $rowsBeforeLine += $this->lineRowCount($this->getPromptForLine($i).($lines[$i] ?? ''));
            }

            $lineText = $lines[$lineNum] ?? '';
            $cursorInLine = \max(0, \min(\mb_strlen($lineText), $buffer->getCursor() - $charsBeforeLine));
            $textBeforeCursor = \mb_substr($lineText, 0, $cursorInLine);

            $absoluteColumn = $promptWidth + DisplayString::width($textBeforeCursor) + 1;
            $calculator = $this->getSoftWrapCalculator();

            return [
                $this->getInputFrameOuterRowCount() + $historyRowOffset + $rowsBeforeLine + $calculator->rowOffsetForAbsoluteColumn($absoluteColumn),
                $calculator->normalizeColumn($absoluteColumn),
            ];
        }

        $textBeforeCursor = \mb_substr($text, 0, $buffer->getCursor());
        $absoluteColumn = $this->getPromptWidthForLine(0, $this->terminal->getFormatter())
            + DisplayString::width($textBeforeCursor) + 1;
        $calculator = $this->getSoftWrapCalculator();

        return [
            $this->getInputFrameOuterRowCount() + $historyRowOffset + $calculator->rowOffsetForAbsoluteColumn($absoluteColumn),
            $calculator->normalizeColumn($absoluteColumn),
        ];
    }

    /**
     * Sync a new frame to the terminal.
     *
     * @param string[] $newFrame
     */
    private function syncFrame(array $newFrame, int $cursorRow, int $cursorColumn): void
    {
        $terminalWidth = $this->getTerminalWidth();
        $terminalHeight = \max(1, $this->terminal->getHeight());
        $sizeChanged = $this->lastTerminalWidth !== null && (
            $terminalWidth !== $this->lastTerminalWidth || $terminalHeight !== $this->lastTerminalHeight
        );
        $dirty = $this->terminal->isDirty();

        $oldFrame = ($sizeChanged || $dirty) ? [] : $this->previousFrame;
        $firstChangedLine = $this->findFirstChangedLine($oldFrame, $newFrame);

        // Wrapped row tracking is invalid after row-affecting out-of-band writes/resizes,
        // or if we can no longer trust the previous frame contents.
        if ($sizeChanged || $this->terminal->isCursorRowUnknown()) {
            $this->cursorFrameRow = 0;
        }

        $this->terminal->beginFrameRender();

        try {
            $currentRow = $this->cursorFrameRow;

            if ($firstChangedLine !== null) {
                $oldStartRow = $this->getRowOffsetBeforeLine($oldFrame, $firstChangedLine);
                $newStartRow = $this->getRowOffsetBeforeLine($newFrame, $firstChangedLine);

                $currentRow = $this->moveCursorToRow($currentRow, $oldStartRow);

                $this->terminal->write("\r");
                $this->terminal->clearToEndOfScreen();

                $suffix = \array_slice($newFrame, $firstChangedLine);
                foreach ($suffix as $i => $line) {
                    if ($i > 0) {
                        $this->terminal->write("\n");
                    }
                    $this->terminal->write($line);
                }

                $currentRow = empty($suffix)
                    ? $newStartRow
                    : $newStartRow + $this->getFrameRowCount($suffix) - 1;
            }

            $currentRow = $this->moveCursorToRow($currentRow, $cursorRow);

            $this->terminal->moveCursorToColumn($cursorColumn);

            $this->previousFrame = $newFrame;
            $this->cursorFrameRow = $cursorRow;
            $this->lastTerminalWidth = $terminalWidth;
            $this->lastTerminalHeight = $terminalHeight;
        } finally {
            $this->terminal->endFrameRender();
        }
    }

    /**
     * Count how many terminal rows a frame occupies after soft wrapping.
     *
     * @param string[] $frame
     */
    private function getFrameRowCount(array $frame): int
    {
        $rows = 0;
        foreach ($frame as $line) {
            $rows += $this->lineRowCount($line);
        }

        return \max(1, $rows);
    }

    /**
     * Count wrapped terminal rows for a single logical line.
     */
    private function lineRowCount(string $line): int
    {
        if (isset($this->lineRowCache[$line])) {
            return $this->lineRowCache[$line];
        }

        $width = DisplayString::widthWithoutAnsi($line);
        $rows = $this->getSoftWrapCalculator()->rowCountForDisplayWidth($width);
        $this->lineRowCache[$line] = $rows;

        return $rows;
    }

    /**
     * Format input into ANSI-safe lines for prompt rendering.
     *
     * @return string[]
     */
    private function formatInputLines(string $text, ?string $historySearchTerm = null, bool $isCommand = false): array
    {
        if ($text === '') {
            return [''];
        }

        if ($historySearchTerm !== null) {
            return \explode("\n", $this->highlightSearchTerm($text, $historySearchTerm));
        }

        if (!$this->useSyntaxHighlighting) {
            return \explode("\n", $text);
        }

        if ($isCommand) {
            return $this->commandHighlighter->highlightLines($text, $this->terminal->getFormatter());
        }

        return CodeFormatter::formatInputLines($text, $this->terminal->getFormatter());
    }

    private function getTerminalWidth(): int
    {
        return \max(1, $this->terminal->getWidth());
    }

    /**
     * Recalculate the cached history row count after line row cache invalidation.
     */
    private function recalculateHistoryRowCount(): void
    {
        $this->historyRowCount = 0;
        foreach ($this->historyLines as $line) {
            $this->historyRowCount += $this->lineRowCount($line);
        }
    }

    private function getSoftWrapCalculator(): SoftWrapCalculator
    {
        $width = $this->getTerminalWidth();
        if ($this->softWrapCalculator === null || $this->softWrapCachedWidth !== $width) {
            $this->softWrapCalculator = new SoftWrapCalculator($width);
            $this->softWrapCachedWidth = $width;
            $this->lineRowCache = [];
            $this->recalculateHistoryRowCount();
        }

        return $this->softWrapCalculator;
    }

    /**
     * Find the first logical line that differs between two frames.
     *
     * @param string[] $oldFrame
     * @param string[] $newFrame
     */
    private function findFirstChangedLine(array $oldFrame, array $newFrame): ?int
    {
        $oldCount = \count($oldFrame);
        $newCount = \count($newFrame);
        $sharedCount = \min($oldCount, $newCount);

        for ($i = 0; $i < $sharedCount; $i++) {
            if ($oldFrame[$i] !== $newFrame[$i]) {
                return $i;
            }
        }

        if ($oldCount !== $newCount) {
            return $sharedCount;
        }

        return null;
    }

    /**
     * Get total wrapped rows occupied by lines before the given logical line index.
     *
     * @param string[] $frame
     */
    private function getRowOffsetBeforeLine(array $frame, int $lineIndex): int
    {
        $rows = 0;
        $lineIndex = \max(0, \min($lineIndex, \count($frame)));

        for ($i = 0; $i < $lineIndex; $i++) {
            $rows += $this->lineRowCount($frame[$i]);
        }

        return $rows;
    }

    /**
     * Move cursor vertically between wrapped frame rows.
     */
    private function moveCursorToRow(int $currentRow, int $targetRow): int
    {
        if ($targetRow < $currentRow) {
            $this->terminal->moveCursorUp($currentRow - $targetRow);
        } elseif ($targetRow > $currentRow) {
            $this->terminal->moveCursorDown($targetRow - $currentRow);
        }

        return $targetRow;
    }
}
