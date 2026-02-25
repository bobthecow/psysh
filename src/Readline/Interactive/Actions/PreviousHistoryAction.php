<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Actions;

use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;

/**
 * Navigate to previous history entry (Up arrow).
 *
 * Fish/ZSH-style behavior: in multi-line mode, first moves cursor to previous
 * line within the buffer. Only navigates to previous history entry when
 * already on the first line.
 */
class PreviousHistoryAction implements ActionInterface
{
    private History $history;

    public function __construct(History $history)
    {
        $this->history = $history;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Buffer $buffer, Terminal $terminal, Readline $readline): bool
    {
        if ($readline->isMultilineMode() && !$buffer->isOnFirstLine()) {
            $buffer->moveToPreviousLine();

            return true;
        }

        $saveTempEntry = !$this->history->isInHistory() && !$buffer->isEmpty();
        $entry = $this->history->getPrevious();

        if ($entry === null) {
            $terminal->bell();

            return true;
        }

        // Save current input before navigating away so we can restore it
        if ($saveTempEntry) {
            $this->history->saveTemporaryEntry($buffer->getText());
        }

        $buffer->setText($entry);
        $readline->reconstructMultiLineFromHistory($entry);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'previous-history';
    }
}
