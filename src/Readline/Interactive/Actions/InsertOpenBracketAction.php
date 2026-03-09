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
 * Insert an opening bracket with auto-closing.
 */
class InsertOpenBracketAction implements ActionInterface
{
    private string $bracket;

    public function __construct(string $bracket)
    {
        $this->bracket = $bracket;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Buffer $buffer, Terminal $terminal, Readline $readline): bool
    {
        if (BracketPair::shouldAutoClose($this->bracket, $buffer)) {
            $closingBracket = BracketPair::getClosingBracket($this->bracket);
            $buffer->insert($this->bracket.$closingBracket);
            $buffer->moveCursorLeft(1);
        } else {
            $buffer->insert($this->bracket);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'insert-open-bracket';
    }
}
