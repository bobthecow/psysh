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

use Psy\Readline\Interactive\Terminal;

/**
 * Input event queue with pushback/replay support.
 */
class InputQueue
{
    private Terminal $terminal;

    /** @var InputEvent[] */
    private array $bufferedEvents = [];

    public function __construct(Terminal $terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * Read the next input event, replaying buffered events first.
     */
    public function read(): InputEvent
    {
        if (!empty($this->bufferedEvents)) {
            return \array_shift($this->bufferedEvents);
        }

        return $this->normalize($this->terminal->readEvent());
    }

    /**
     * Push events back so they are replayed in argument order.
     */
    public function replay(InputEvent $event, InputEvent ...$events): void
    {
        \array_unshift($events, $event);
        for ($i = \count($events) - 1; $i >= 0; $i--) {
            \array_unshift($this->bufferedEvents, $this->normalize($events[$i]));
        }
    }

    private function normalize(InputEvent $event): InputEvent
    {
        return $event instanceof KeyEvent ? $event->normalized() : $event;
    }
}
