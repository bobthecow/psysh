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

use Psy\Readline\Interactive\Helper\BracketPair;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;

/**
 * Insert a line break without executing the current buffer.
 */
class InsertLineBreakAction implements ActionInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(Buffer $buffer, Terminal $terminal, Readline $readline): bool
    {
        if (!$readline->isMultilineMode()) {
            $readline->enterMultilineMode();
        }

        $indent = $buffer->calculateIndentBeforeCursor();

        $buffer->insert("\n");
        if ($indent !== '') {
            $buffer->insert($indent);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'insert-line-break';
    }
}
