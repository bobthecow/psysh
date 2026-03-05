<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive;

use Psy\Exception\BreakException;
use Psy\Readline\Interactive\Actions\ExpandHistoryOnTabAction;
use Psy\Readline\Interactive\Actions\HistoryExpansionAction;
use Psy\Readline\Interactive\Actions\InsertIndentOnTabAction;
use Psy\Readline\Interactive\Actions\SelfInsertAction;
use Psy\Readline\Interactive\Actions\TabAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\InputQueue;
use Psy\Readline\Interactive\Input\Key;
use Psy\Readline\Interactive\Input\KeyBindings;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\OverlayViewport;
use Psy\Readline\Interactive\Suggestion\SuggestionEngine;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Shell;

/**
 * Interactive readline implementation.
 *
 * A pure-PHP readline implementation with better control over cursor
 * positioning, tab completion display, and terminal interaction.
 */
class Readline
{
    private const MODE_NORMAL = 'normal';
    private const MODE_SEARCH = 'search';
    private const MODE_MENU = 'menu';

    private Terminal $terminal;
    private InputQueue $inputQueue;
    private KeyBindings $bindings;
    private History $history;
    private bool $multilineMode = false;
    private ?Shell $shell = null;
    private bool $requireSemicolons = false;

    private string $mode = self::MODE_NORMAL;
    private string $searchQuery = '';
    /** @var string[] */
    private array $searchMatches = [];
    private int $currentMatchIndex = -1;
    private ?string $savedBufferText = null;
    private int $savedCursorPosition = 0;

    private ?TabAction $tabAction = null;
    private ?ExpandHistoryOnTabAction $expandHistoryAction = null;

    private bool $smartBrackets = true;

    private bool $continueFrame = false;
    private int $lastSubmitEscapeRows = 0;
    private ?string $lastSubmittedText = null;

    private bool $useSuggestions = false;
    private SuggestionEngine $suggestionEngine;
    private OverlayViewport $overlayViewport;
    private FrameRenderer $frameRenderer;
    private ?SuggestionResult $currentSuggestion = null;

    /**
     * Create a new interactive Readline instance.
     */
    public function __construct(Terminal $terminal, ?KeyBindings $bindings = null, ?History $history = null)
    {
        $this->terminal = $terminal;
        $this->inputQueue = new InputQueue($this->terminal);
        $this->history = $history ?? new History();

        $this->bindings = $bindings ?? KeyBindings::createDefault($this->history, $this->smartBrackets);

        $this->suggestionEngine = new SuggestionEngine($this->history);
        $this->overlayViewport = new OverlayViewport($this->terminal);
        $this->frameRenderer = new FrameRenderer($this->terminal, $this->overlayViewport);
    }

    /**
     * Set the single-line prompt string.
     */
    public function setPrompt(string $prompt): void
    {
        $this->frameRenderer->setSingleLinePrompt($prompt);
    }

    /**
     * Set the multi-line continuation prompt.
     */
    public function setMultilinePrompt(string $prompt): void
    {
        $this->frameRenderer->setMultilinePrompt($prompt);
    }

    /**
     * Enable compact input frame rendering.
     */
    public function setCompactInputFrame(bool $compact): void
    {
        $this->frameRenderer->setCompactInputFrame($compact);
    }

    /**
     * Check whether compact input frame rendering is enabled.
     */
    public function isInputFrameCompact(): bool
    {
        return $this->frameRenderer->isCompactInputFrame();
    }

    /**
     * Get number of outer rows surrounding input content.
     */
    public function getInputFrameOuterRowCount(): int
    {
        return $this->frameRenderer->getInputFrameOuterRowCount();
    }

    /**
     * Get active prompt display width for the cursor's current line.
     */
    public function getPromptWidthForCurrentLine(Buffer $buffer): int
    {
        return $this->frameRenderer->getPromptWidthForCurrentLine($buffer);
    }

    /**
     * Set the Shell reference.
     */
    public function setShell(Shell $shell): void
    {
        $this->shell = $shell;

        if ($this->expandHistoryAction !== null) {
            $this->expandHistoryAction->setHistoryExpansion(new HistoryExpansionAction($this->history, $this->shell));
        }
    }

    /**
     * Set whether to require semicolons on all statements.
     *
     * By default, PsySH automatically inserts semicolons. When set to true,
     * semicolons are strictly required.
     */
    public function setRequireSemicolons(bool $require): void
    {
        $this->requireSemicolons = $require;
    }

    /**
     * Set the CompletionEngine for context-aware tab completion.
     */
    public function setCompletionEngine(\Psy\Completion\CompletionEngine $completionEngine): void
    {
        if ($this->tabAction === null) {
            $this->tabAction = new TabAction($completionEngine, $this->smartBrackets);
            $this->expandHistoryAction = new ExpandHistoryOnTabAction(new HistoryExpansionAction($this->history, $this->shell));
            $this->bindings->bind(
                'control:i',
                new InsertIndentOnTabAction(),
                $this->expandHistoryAction,
                $this->tabAction
            );
        } else {
            $this->tabAction->setCompleter($completionEngine);
        }
    }

    /**
     * Check if the current input is a PsySH command.
     */
    public function isCommand(string $input): bool
    {
        if ($this->shell === null) {
            return false;
        }

        if (\preg_match('/([^\s]+?)(?:\s|$)/A', \ltrim($input), $match)) {
            return $this->shell->has($match[1]);
        }

        return false;
    }

    /**
     * Check if currently in multi-line mode.
     */
    public function isMultilineMode(): bool
    {
        return $this->multilineMode;
    }

    /**
     * Check if input is in an open string or comment.
     */
    public function isInOpenStringOrComment(string $input): bool
    {
        $tokens = @\token_get_all('<?php '.$input);
        $last = \array_pop($tokens);

        return $last === '"' || $last === '`' ||
            (\is_array($last) && \in_array($last[0], [\T_ENCAPSED_AND_WHITESPACE, \T_START_HEREDOC, \T_COMMENT]));
    }

    /**
     * Enter multi-line mode.
     */
    public function enterMultilineMode(): void
    {
        $this->multilineMode = true;
    }

    /**
     * Exit multi-line mode.
     */
    public function exitMultilineMode(): void
    {
        $this->multilineMode = false;
    }

    /**
     * Cancel multi-line mode without executing.
     */
    public function cancelMultilineMode(): void
    {
        if (!$this->multilineMode) {
            return;
        }

        $this->multilineMode = false;
    }

    /**
     * Sync multiline mode with buffer content and invalidate the frame on transition.
     */
    private function syncMultilineMode(string $text): void
    {
        $isMultiline = \strpos($text, "\n") !== false;

        if ($this->multilineMode === $isMultiline) {
            return;
        }

        if ($isMultiline) {
            $this->enterMultilineMode();
        } else {
            $this->exitMultilineMode();
        }

        $this->terminal->invalidateFrame();
    }

    /**
     * Read a line of input.
     *
     * @return string|false The input line, or false on EOF
     */
    public function readline()
    {
        $this->mode = self::MODE_NORMAL;
        $this->resetSearchState();
        $this->clearSuggestion();

        if ($this->continueFrame && $this->lastSubmittedText !== null) {
            // Rewind the cursor past the escape newlines written by SubmitLineAction.
            // Wrap in a frame render so the movement doesn't mark the terminal dirty
            // (we're restoring the cursor to its known position, not making out-of-band changes).
            $this->terminal->beginFrameRender();
            if ($this->lastSubmitEscapeRows > 0) {
                $this->terminal->moveCursorUp($this->lastSubmitEscapeRows);
            }
            $this->terminal->endFrameRender();

            $this->frameRenderer->addHistoryLines($this->lastSubmittedText);

            $this->continueFrame = false;
        } else {
            $this->frameRenderer->reset();
        }

        $this->multilineMode = false;

        $buffer = new Buffer($this->requireSemicolons);
        $this->display($buffer);

        try {
            while (true) {
                $key = $this->inputQueue->read();

                if ($key->isEof()) {
                    $this->terminal->write("\n");

                    return false;
                }

                // Any keystroke clears error mode
                $this->setInputFrameError(false);

                if ($key->isPaste()) {
                    $this->handlePastedContent($key->getValue(), $buffer);
                    $this->syncMultilineMode($buffer->getText());
                    $this->display($buffer);
                    continue;
                }

                if ($this->mode === self::MODE_SEARCH) {
                    if (!$this->handleSearchModeInput($key, $buffer)) {
                        $this->syncMultilineMode($buffer->getText());
                        $this->display($buffer);
                    } else {
                        $this->displaySearchPrompt();
                    }
                    continue;
                }

                $action = $this->bindings->get($key);

                if ($action === null && $key->isChar()) {
                    $action = new SelfInsertAction($key->getValue());
                }

                if ($action !== null) {
                    $continue = $action->execute($buffer, $this->terminal, $this);

                    if ($continue) {
                        $this->syncMultilineMode($buffer->getText());
                        $this->updateSuggestion($buffer);
                        $this->display($buffer);
                    } else {
                        $line = $buffer->getText();
                        $this->clearSuggestion();

                        if (!$this->isCommand($line) || $this->isInOpenStringOrComment($line)) {
                            $this->exitMultilineMode();
                        }

                        $this->history->reset();
                        $this->lastSubmittedText = $line;

                        return $line;
                    }
                } else {
                    $this->terminal->bell();
                }
            }
        } catch (BreakException $e) {
            // Shell.php will write the newline
            return false;
        }
    }

    /**
     * Handle pasted content (potentially multi-line).
     */
    private function handlePastedContent(string $content, Buffer $buffer): void
    {
        $content = \strtr(\str_replace("\r\n", "\n", $content), "\r", "\n");

        $buffer->insert($content);
    }

    /**
     * Display the prompt and buffer.
     */
    private function display(Buffer $buffer): void
    {
        $this->frameRenderer->render($buffer, $this->currentSuggestion);
    }

    /**
     * Clear the overlay and re-render.
     */
    public function clearOverlay(Buffer $buffer): void
    {
        $this->frameRenderer->clearOverlay($buffer);
    }

    /**
     * Render overlay lines and redraw the frame.
     *
     * @param string[] $lines
     */
    public function renderOverlay(Buffer $buffer, array $lines): void
    {
        $this->frameRenderer->setOverlayLines($lines);
        $this->display($buffer);
    }

    /**
     * Read the next key event from the queue.
     */
    public function readNextKey(): Key
    {
        return $this->inputQueue->read();
    }

    /**
     * Replay a key event so the main loop can consume it.
     */
    public function replayKey(Key $key): void
    {
        $this->inputQueue->replay($key);
    }

    /**
     * Internal accessor for interactive readline internals and tests.
     *
     * @internal
     */
    public function getTabAction(): ?TabAction
    {
        return $this->tabAction;
    }

    /**
     * Get available overlay rows for completion/search UI.
     */
    public function getOverlayAvailableRows(bool $collapsed): int
    {
        return $this->overlayViewport->getAvailableRows($collapsed);
    }

    /**
     * Set the input frame error state (red-tinted background for syntax errors).
     */
    public function setInputFrameError(bool $error): void
    {
        $this->frameRenderer->setErrorMode($error);
    }

    /**
     * Set whether the next readline() call should continue the current frame.
     */
    public function setContinueFrame(bool $continue): void
    {
        $this->continueFrame = $continue;
    }

    /**
     * Store the number of newlines written by SubmitLineAction to escape the frame.
     */
    public function setLastSubmitEscapeRows(int $rows): void
    {
        $this->lastSubmitEscapeRows = $rows;
    }

    /**
     * Get the history.
     */
    public function getHistory(): History
    {
        return $this->history;
    }

    /**
     * Enable or disable inline suggestions.
     */
    public function setUseSuggestions(bool $enabled): void
    {
        $this->useSuggestions = $enabled;
    }

    /**
     * Get the suggestion engine.
     */
    public function getSuggestionEngine(): SuggestionEngine
    {
        return $this->suggestionEngine;
    }

    /**
     * Get the current suggestion.
     */
    public function getCurrentSuggestion(): ?SuggestionResult
    {
        return $this->currentSuggestion;
    }

    /**
     * Clear the current suggestion.
     */
    public function clearSuggestion(): void
    {
        $this->currentSuggestion = null;
        $this->suggestionEngine->clearCache();
    }

    /**
     * Update suggestion based on current buffer state.
     */
    private function updateSuggestion(Buffer $buffer): void
    {
        if (!$this->useSuggestions) {
            return;
        }

        if ($this->mode !== self::MODE_NORMAL || $this->multilineMode) {
            $this->clearSuggestion();

            return;
        }

        $this->currentSuggestion = $this->suggestionEngine->getSuggestion(
            $buffer->getText(),
            $buffer->getCursor()
        );
    }

    /**
     * Check if currently in search mode.
     */
    public function isInSearchMode(): bool
    {
        return $this->mode === self::MODE_SEARCH;
    }

    /**
     * Enter search mode.
     */
    public function enterSearchMode(): void
    {
        $this->mode = self::MODE_SEARCH;
        $this->resetSearchState();
    }

    /**
     * Exit search mode.
     */
    public function exitSearchMode(): void
    {
        if ($this->mode === self::MODE_SEARCH) {
            $this->mode = self::MODE_NORMAL;
        }
        $this->resetSearchState();
        $this->savedBufferText = null;
        $this->savedCursorPosition = 0;
    }

    /**
     * Enter completion menu mode.
     */
    public function enterMenuMode(): void
    {
        $this->mode = self::MODE_MENU;
    }

    /**
     * Exit completion menu mode.
     */
    public function exitMenuMode(): void
    {
        if ($this->mode === self::MODE_MENU) {
            $this->mode = self::MODE_NORMAL;
        }
    }

    /**
     * Reset search query and match state.
     */
    private function resetSearchState(): void
    {
        $this->searchQuery = '';
        $this->searchMatches = [];
        $this->currentMatchIndex = -1;
    }

    /**
     * Update search query and find matches.
     */
    public function updateSearchQuery(string $query): void
    {
        $this->searchQuery = $query;

        $this->searchMatches = $this->history->search($query, true);
        $this->currentMatchIndex = empty($this->searchMatches) ? -1 : 0;
    }

    /**
     * Add a character to the search query.
     */
    public function addSearchChar(string $char): void
    {
        $this->updateSearchQuery($this->searchQuery.$char);
    }

    /**
     * Remove last character from search query.
     */
    public function removeSearchChar(): void
    {
        if ($this->searchQuery !== '') {
            $this->updateSearchQuery(\substr($this->searchQuery, 0, -1));
        }
    }

    /**
     * Find next match in search results (older entries).
     */
    public function findNextSearchMatch(): void
    {
        if (empty($this->searchMatches)) {
            $this->terminal->bell();

            return;
        }

        $this->currentMatchIndex++;
        if ($this->currentMatchIndex >= \count($this->searchMatches)) {
            $this->currentMatchIndex = 0;
            $this->terminal->bell();
        }
    }

    /**
     * Find previous match in search results (newer entries).
     */
    public function findPreviousSearchMatch(): void
    {
        if (empty($this->searchMatches)) {
            $this->terminal->bell();

            return;
        }

        $this->currentMatchIndex--;
        if ($this->currentMatchIndex < 0) {
            $this->currentMatchIndex = \count($this->searchMatches) - 1;
            $this->terminal->bell();
        }
    }

    /**
     * Get the current search match.
     *
     * @return string|null The current match, or null if no match
     */
    public function getCurrentSearchMatch(): ?string
    {
        if (empty($this->searchMatches) || $this->currentMatchIndex < 0) {
            return null;
        }

        return $this->searchMatches[$this->currentMatchIndex] ?? null;
    }

    /**
     * Get the search query.
     */
    public function getSearchQuery(): string
    {
        return $this->searchQuery;
    }

    /**
     * Accept current search match and exit search mode.
     */
    public function acceptSearchMatch(Buffer $buffer): void
    {
        $match = $this->getCurrentSearchMatch();
        if ($match !== null) {
            $buffer->clear();
            $buffer->insert($match);
        }

        $this->exitSearchMode();
    }

    /**
     * Cancel search and restore original buffer.
     */
    public function cancelSearch(Buffer $buffer): void
    {
        if ($this->savedBufferText !== null) {
            $buffer->clear();
            $buffer->insert($this->savedBufferText);
            $buffer->setCursor($this->savedCursorPosition);
        }

        $this->exitSearchMode();
    }

    /**
     * Save current buffer before starting search.
     */
    public function saveBufferForSearch(Buffer $buffer): void
    {
        $this->savedBufferText = $buffer->getText();
        $this->savedCursorPosition = $buffer->getCursor();
    }

    /**
     * Handle input while in search mode.
     *
     * @return bool True if still in search mode, false if exited
     */
    private function handleSearchModeInput(Key $key, Buffer $buffer): bool
    {
        $value = $key->getValue();

        if ($value === "\x07" || $value === "\x1b") { // Ctrl-G or Escape
            $this->cancelSearch($buffer);

            return false;
        } elseif ($value === "\r" || $value === "\n") {
            $this->acceptSearchMatch($buffer);

            return false;
        } elseif ($value === "\x12") { // Ctrl-R
            $this->findNextSearchMatch();
            $this->applySearchMatch($buffer);

            return true;
        } elseif ($value === "\x13") { // Ctrl-S
            $this->findPreviousSearchMatch();
            $this->applySearchMatch($buffer);

            return true;
        } elseif ($value === "\x08" || $value === "\x7f") { // Backspace
            $this->removeSearchChar();
            $this->applySearchMatch($buffer, true);

            return true;
        } elseif ($key->isChar() && !$key->isControl()) {
            $this->addSearchChar($value);
            $this->applySearchMatch($buffer);

            return true;
        } else {
            $this->acceptSearchMatch($buffer);
            $this->replayKey($key);

            return false;
        }
    }

    /**
     * Apply the current search match to the buffer.
     */
    private function applySearchMatch(Buffer $buffer, bool $restoreOnEmpty = false): void
    {
        $match = $this->getCurrentSearchMatch();
        if ($match !== null) {
            $buffer->clear();
            $buffer->insert($match);
        } elseif ($restoreOnEmpty && $this->searchQuery === '' && $this->savedBufferText !== null) {
            $buffer->clear();
            $buffer->insert($this->savedBufferText);
        }
    }

    /**
     * Render the reverse-i-search prompt.
     */
    private function displaySearchPrompt(): void
    {
        $match = $this->getCurrentSearchMatch();
        $prompt = '(reverse-i-search)`'.$this->searchQuery."': ";

        if ($match !== null) {
            $firstLine = \strstr($match, "\n", true);
            $prompt .= $firstLine !== false ? $firstLine.' ...' : $match;
        } elseif ($this->searchQuery !== '') {
            $prompt .= '(no matches)';
        }

        $this->frameRenderer->renderSearchPrompt($prompt);
    }
}
