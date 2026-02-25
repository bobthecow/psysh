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
use Psy\Readline\Interactive\Terminal;
use Symfony\Component\Console\Helper\Helper;

/**
 * Frame-buffered renderer for interactive readline.
 *
 * Builds the entire visible area (input lines + overlay) as an array of
 * strings, then diffs against the previous frame and writes minimal updates.
 * Uses only relative cursor movement (never save/restore cursor) so terminal
 * scrolling can't break cursor positioning.
 */
class FrameRenderer
{
    private Terminal $terminal;
    private OverlayViewport $viewport;

    private string $singleLinePrompt = '> ';
    private string $multilinePrompt = '. ';

    /** @var string[] Lines currently displayed on the terminal. */
    private array $previousFrame = [];

    /** Which frame line the terminal cursor is currently on (0-indexed). */
    private int $cursorFrameLine = 0;

    /** @var string[] Overlay lines to display below the input. */
    private array $overlayLines = [];

    public function __construct(Terminal $terminal, OverlayViewport $viewport)
    {
        $this->terminal = $terminal;
        $this->viewport = $viewport;
    }

    /**
     * Set the single-line prompt string.
     */
    public function setSingleLinePrompt(string $prompt): void
    {
        $this->singleLinePrompt = $prompt;
    }

    /**
     * Set the multi-line continuation prompt.
     */
    public function setMultilinePrompt(string $prompt): void
    {
        $this->multilinePrompt = $prompt;
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
    public function clearOverlay(Buffer $buffer, bool $multilineMode): void
    {
        $this->overlayLines = [];
        $this->render($buffer, $multilineMode);
    }

    /**
     * Render the full frame (input + overlay) to the terminal.
     */
    public function render(Buffer $buffer, bool $multilineMode): void
    {
        $isMultiline = $multilineMode && \strpos($buffer->getText(), "\n") !== false;
        $inputLines = $this->buildInputLines($buffer, $isMultiline);

        $this->viewport->setInputLineCount(\count($inputLines));

        $frame = \array_merge($inputLines, $this->overlayLines);

        $cursorLine = $isMultiline ? $buffer->getCurrentLineNumber() : 0;
        $cursorColumn = $this->getCursorColumn($buffer, $isMultiline);

        $this->syncFrame($frame, $cursorLine, $cursorColumn);
        $this->terminal->flush();
    }

    /**
     * Render a search prompt in place of the normal input line.
     */
    public function renderSearchPrompt(string $prompt): void
    {
        $this->syncFrame([$prompt], 0, Helper::width($prompt) + 1);
        $this->terminal->flush();
    }

    /**
     * Reset renderer state (call when starting a new readline session).
     */
    public function reset(): void
    {
        $this->previousFrame = [];
        $this->cursorFrameLine = 0;
        $this->overlayLines = [];
    }

    /**
     * Build the input section of the frame.
     *
     * @return string[]
     */
    private function buildInputLines(Buffer $buffer, bool $isMultiline): array
    {
        $text = $buffer->getText();

        if ($isMultiline) {
            $lines = \explode("\n", $text);
            $result = [];
            foreach ($lines as $i => $line) {
                $prompt = ($i === 0) ? $this->singleLinePrompt : $this->multilinePrompt;
                $result[] = $prompt.$line;
            }

            return $result;
        }

        $line = $this->singleLinePrompt.$text;

        return [$line];
    }

    /**
     * Get the terminal column where the cursor should be (1-indexed).
     */
    private function getCursorColumn(Buffer $buffer, bool $isMultiline): int
    {
        $text = $buffer->getText();

        if ($isMultiline) {
            $lines = \explode("\n", $text);
            $lineNum = $buffer->getCurrentLineNumber();
            $prompt = ($lineNum === 0) ? $this->singleLinePrompt : $this->multilinePrompt;

            $charsBeforeLine = 0;
            for ($i = 0; $i < $lineNum; $i++) {
                $charsBeforeLine += \mb_strlen($lines[$i]) + 1;
            }

            $cursorInLine = $buffer->getCursor() - $charsBeforeLine;
            $textBeforeCursor = \mb_substr($lines[$lineNum], 0, $cursorInLine);

            return Helper::width($prompt) + Helper::width($textBeforeCursor) + 1;
        }

        $textBeforeCursor = \mb_substr($text, 0, $buffer->getCursor());

        return Helper::width($this->singleLinePrompt) + Helper::width($textBeforeCursor) + 1;
    }

    /**
     * Sync a new frame to the terminal with minimal writes.
     *
     * @param string[] $newFrame
     */
    private function syncFrame(array $newFrame, int $cursorLine, int $cursorColumn): void
    {
        // If something wrote to the terminal outside of a frame render,
        // we can't trust previousFrame — force a full repaint.
        if ($this->terminal->isDirty()) {
            $this->previousFrame = [];
        }

        // If the cursor row was moved out-of-band, we don't know where
        // it is — reset to 0 so the renderer starts fresh.
        if ($this->terminal->isCursorRowUnknown()) {
            $this->cursorFrameLine = 0;
        }

        $this->terminal->beginFrameRender();

        try {
            if ($this->cursorFrameLine > 0) {
                $this->terminal->moveCursorUp($this->cursorFrameLine);
            }

            $newCount = \count($newFrame);
            $oldCount = \count($this->previousFrame);
            $maxLines = \max($newCount, $oldCount);

            for ($i = 0; $i < $maxLines; $i++) {
                if ($i > 0) {
                    $this->terminal->write("\n");
                }

                if ($i < $newCount) {
                    $newLine = $newFrame[$i];
                    $oldLine = $this->previousFrame[$i] ?? null;

                    if ($newLine !== $oldLine) {
                        $this->terminal->write("\r");
                        $this->terminal->clearToEndOfLine();
                        $this->terminal->write($newLine);
                    }
                } else {
                    // Frame shrank — clear leftover lines
                    $this->terminal->write("\r");
                    $this->terminal->clearToEndOfLine();
                }
            }

            $currentLine = $maxLines - 1;
            if ($cursorLine < $currentLine) {
                $this->terminal->moveCursorUp($currentLine - $cursorLine);
            }

            $this->terminal->moveCursorToColumn($cursorColumn);

            $this->cursorFrameLine = $cursorLine;
            $this->previousFrame = $newFrame;
        } finally {
            $this->terminal->endFrameRender();
        }
    }
}
