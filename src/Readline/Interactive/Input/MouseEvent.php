<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Readline\Interactive\Input;

/**
 * Represents a mouse action at a terminal coordinate.
 */
class MouseEvent extends InputEvent
{
    public const ACTION_MOVE = 'move';
    public const ACTION_PRESS_LEFT = 'press-left';
    public const ACTION_PRESS_MIDDLE = 'press-middle';
    public const ACTION_PRESS_RIGHT = 'press-right';
    public const ACTION_RELEASE_LEFT = 'release-left';
    public const ACTION_RELEASE_MIDDLE = 'release-middle';
    public const ACTION_RELEASE_RIGHT = 'release-right';
    public const ACTION_WHEEL_UP = 'wheel-up';
    public const ACTION_WHEEL_DOWN = 'wheel-down';
    public const ACTION_WHEEL_LEFT = 'wheel-left';
    public const ACTION_WHEEL_RIGHT = 'wheel-right';

    private string $action;
    private int $column;
    private int $row;

    public function __construct(string $action, int $column, int $row)
    {
        $this->action = $action;
        $this->column = $column;
        $this->row = $row;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the 1-indexed terminal column.
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Get the 1-indexed terminal row.
     */
    public function getRow(): int
    {
        return $this->row;
    }
}
