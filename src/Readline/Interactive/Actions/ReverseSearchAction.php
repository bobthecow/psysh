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
 * Action for interactive reverse history search (Ctrl-R).
 */
class ReverseSearchAction implements ActionInterface
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
        if ($readline->isInSearchMode()) {
            $readline->findNextSearchMatch();

            $match = $readline->getCurrentSearchMatch();
            if ($match !== null) {
                $buffer->clear();
                $buffer->insert($match);
            }
        } else {
            $readline->saveBufferForSearch($buffer);
            $readline->enterSearchMode();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'reverse-search-history';
    }
}
