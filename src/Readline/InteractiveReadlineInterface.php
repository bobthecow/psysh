<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline;

use Psy\Completion\CompletionEngine;
use Psy\Output\Theme;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Terminal;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for interactive readline implementations with additional features.
 *
 * This interface extends the base Readline interface to provide automatic
 * semicolon handling, custom prompts for multiline input, and more advanced
 * interaction.
 */
interface InteractiveReadlineInterface extends Readline
{
    /**
     * Set whether to require semicolons on all statements.
     *
     * When set to false (default), the readline implementation may
     * automatically insert semicolons where appropriate. When set to true,
     * semicolons are strictly required.
     */
    public function setRequireSemicolons(bool $require): void;

    /**
     * Set the theme (currently used for prompt configuration).
     */
    public function setTheme(Theme $theme): void;

    /**
     * Enable or disable bracketed paste mode.
     */
    public function setBracketedPaste(bool $enabled): void;

    /**
     * Enable or disable inline suggestions.
     */
    public function setUseSuggestions(bool $enabled): void;

    /**
     * Set the CompletionEngine for context-aware tab completion and autosuggestions.
     */
    public function setCompletionEngine(CompletionEngine $completionEngine): void;

    /**
     * Set the output stream.
     */
    public function setOutput(OutputInterface $output, ?Terminal $terminal = null): void;

    /**
     * Get the history instance.
     */
    public function getHistory(): History;

    /**
     * Report whether visible output was written since the last input.
     *
     * The readline implementation uses this to decide whether to continue
     * the current input frame or start a fresh one.
     */
    public function setOutputWritten(bool $written): void;
}
