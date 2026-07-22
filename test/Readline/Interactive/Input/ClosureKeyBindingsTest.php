<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Input;

use Psy\Readline\Interactive\Input\ClosureKeyBindings;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Test\TestCase;

class ClosureKeyBindingsTest extends TestCase
{
    public function testBindAndGetSinglePattern(): void
    {
        $bindings = new ClosureKeyBindings();
        $called = 0;
        $bindings->bind(function () use (&$called) { $called++; }, 'char:j');

        $action = $bindings->get(new KeyEvent('j', KeyEvent::TYPE_CHAR));
        $this->assertNotNull($action);
        $action(new KeyEvent('j', KeyEvent::TYPE_CHAR));
        $this->assertSame(1, $called);
    }

    public function testBindAttachesSameActionToMultiplePatterns(): void
    {
        $bindings = new ClosureKeyBindings();
        $called = 0;
        $bindings->bind(function () use (&$called) { $called++; }, 'char:j', 'escape:[B', 'control:n');

        foreach ([
            new KeyEvent('j', KeyEvent::TYPE_CHAR),
            new KeyEvent("\x1b[B", KeyEvent::TYPE_ESCAPE),
            new KeyEvent("\x0e", KeyEvent::TYPE_CONTROL),
        ] as $key) {
            $action = $bindings->get($key);
            $this->assertNotNull($action, (string) $key);
            $action($key);
        }

        $this->assertSame(3, $called);
    }

    public function testGetReturnsNullForUnboundKey(): void
    {
        $bindings = new ClosureKeyBindings();
        $this->assertNull($bindings->get(new KeyEvent('z', KeyEvent::TYPE_CHAR)));
    }
}
