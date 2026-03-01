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

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Layout\DisplayString;
use Psy\Readline\Interactive\Layout\PromptMap;
use Psy\Readline\Interactive\Layout\SoftWrapCalculator;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Symfony\Component\Console\Formatter\OutputFormatter;

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
    private PromptMap $prompts;

    /** @var string[] Lines currently displayed on the terminal. */
    private array $previousFrame = [];

    /** Which wrapped frame row the terminal cursor is currently on (0-indexed). */
    private int $cursorFrameRow = 0;

    /** @var string[] Overlay lines to display below the input. */
    private array $overlayLines = [];

    private bool $compactInputFrame = false;

    private ?int $lastTerminalWidth = null;
    private ?int $lastTerminalHeight = null;
    private ?SoftWrapCalculator $softWrapCalculator = null;
    private ?int $softWrapCachedWidth = null;

    /** @var array<string, int> Cached row counts keyed by line content. */
    private array $lineRowCache = [];

    public function __construct(Terminal $terminal, OverlayViewport $viewport)
    {
        $this->terminal = $terminal;
        $this->viewport = $viewport;
        $this->prompts = new PromptMap();
    }

    /**
     * Set the single-line prompt string.
     */
    public function setSingleLinePrompt(string $prompt): void
    {
        $this->prompts->setSingleLinePrompt($prompt);
    }

    /**
     * Set the multi-line continuation prompt.
     */
    public function setMultilinePrompt(string $prompt): void
    {
        $this->prompts->setMultilinePrompt($prompt);
    }

    /**
     * Enable compact input frame rendering (no gutters/background separators).
     */
    public function setCompactInputFrame(bool $compact): void
    {
        $this->compactInputFrame = $compact;
    }

    /**
     * Check whether compact input frame rendering is enabled.
     */
    public function isCompactInputFrame(): bool
    {
        return $this->compactInputFrame;
    }

    /**
     * Get number of outer rows surrounding input content.
     */
    public function getInputFrameOuterRowCount(): int
    {
        return $this->compactInputFrame ? 0 : self::INPUT_FRAME_PADDING_ROWS;
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

        return $this->prompts->getPromptWidthForLine($lineNumber, $this->terminal->getFormatter());
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
    public function render(Buffer $buffer, ?SuggestionResult $suggestion): void
    {
        $isMultiline = \strpos($buffer->getText(), "\n") !== false;
        $inputLines = $this->buildInputLines($buffer, $isMultiline, $suggestion);

        $this->viewport->setInputRowCount($this->getFrameRowCount($inputLines));

        $frame = \array_merge($inputLines, $this->overlayLines);

        [$cursorRow, $cursorColumn] = $this->getCursorPosition($buffer, $isMultiline);

        $this->syncFrame($frame, $cursorRow, $cursorColumn);
        $this->terminal->flush();
    }

    /**
     * Render a search prompt in place of the normal input line.
     */
    public function renderSearchPrompt(string $prompt): void
    {
        $this->viewport->setInputRowCount($this->lineRowCount($prompt));

        $calculator = $this->getSoftWrapCalculator();
        $absoluteColumn = DisplayString::widthWithoutAnsi($prompt) + 1;
        $cursorRow = $calculator->rowOffsetForAbsoluteColumn($absoluteColumn);
        $cursorColumn = $calculator->normalizeColumn($absoluteColumn);

        $this->syncFrame([$prompt], $cursorRow, $cursorColumn);
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
        $this->lastTerminalWidth = null;
        $this->lastTerminalHeight = null;
        $this->lineRowCache = [];
    }

    /**
     * Build the input section of the frame.
     *
     * @return string[]
     */
    private function buildInputLines(Buffer $buffer, bool $isMultiline, ?SuggestionResult $suggestion): array
    {
        $text = $buffer->getText();

        $contentLines = [];
        if ($isMultiline) {
            $lines = \explode("\n", $text);
            foreach ($lines as $i => $line) {
                $contentLines[] = $this->prompts->getPromptForLine($i).$line;
            }
        } else {
            $line = $this->prompts->getPromptForLine(0).$text;

            if ($suggestion !== null) {
                $line = $this->appendSuggestionGhostText($line, $buffer, $text, $suggestion);
            }

            $contentLines[] = $line;
        }

        if ($this->compactInputFrame) {
            return $contentLines;
        }

        $formatter = $this->terminal->getFormatter();
        $inputFrameStyle = ($formatter->isDecorated() && $formatter->hasStyle('input_frame'))
            ? $formatter->getStyle('input_frame')
            : null;

        $framedLines = [''];
        foreach (['', ...$contentLines, ''] as $line) {
            $lineWithClear = $line.self::CLEAR_TO_END_OF_LINE;
            $framedLines[] = $inputFrameStyle ? $inputFrameStyle->apply($lineWithClear) : $lineWithClear;
        }
        $framedLines[] = '';

        return $framedLines;
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

        $absoluteCursorColumn = $this->prompts->getPromptWidthForLine(0, $this->terminal->getFormatter())
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

        if ($isMultiline) {
            $lines = \explode("\n", $text);
            $lineNum = $buffer->getCurrentLineNumber();
            $promptWidth = $this->prompts->getPromptWidthForLine($lineNum, $this->terminal->getFormatter());

            $charsBeforeLine = 0;
            $rowsBeforeLine = 0;
            for ($i = 0; $i < $lineNum; $i++) {
                $charsBeforeLine += \mb_strlen($lines[$i]) + 1;
                $rowsBeforeLine += $this->lineRowCount($this->prompts->getPromptForLine($i).($lines[$i] ?? ''));
            }

            $lineText = $lines[$lineNum] ?? '';
            $cursorInLine = \max(0, \min(\mb_strlen($lineText), $buffer->getCursor() - $charsBeforeLine));
            $textBeforeCursor = \mb_substr($lineText, 0, $cursorInLine);

            $absoluteColumn = $promptWidth + DisplayString::width($textBeforeCursor) + 1;
            $calculator = $this->getSoftWrapCalculator();

            return [
                $this->getInputFrameOuterRowCount() + $rowsBeforeLine + $calculator->rowOffsetForAbsoluteColumn($absoluteColumn),
                $calculator->normalizeColumn($absoluteColumn),
            ];
        }

        $textBeforeCursor = \mb_substr($text, 0, $buffer->getCursor());
        $absoluteColumn = $this->prompts->getPromptWidthForLine(0, $this->terminal->getFormatter())
            + DisplayString::width($textBeforeCursor) + 1;
        $calculator = $this->getSoftWrapCalculator();

        return [
            $this->getInputFrameOuterRowCount() + $calculator->rowOffsetForAbsoluteColumn($absoluteColumn),
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

    private function getTerminalWidth(): int
    {
        return \max(1, $this->terminal->getWidth());
    }

    private function getSoftWrapCalculator(): SoftWrapCalculator
    {
        $width = $this->getTerminalWidth();
        if ($this->softWrapCalculator === null || $this->softWrapCachedWidth !== $width) {
            $this->softWrapCalculator = new SoftWrapCalculator($width);
            $this->softWrapCachedWidth = $width;
            $this->lineRowCache = [];
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
