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
use Psy\Readline\Interactive\Input\Key;
use Psy\Test\TestCase;

class ClosureKeyBindingsTest extends TestCase
{
    public function testBindAndGetSinglePattern(): void
    {
        $bindings = new ClosureKeyBindings();
        $called = 0;
        $bindings->bind(function () use (&$called) { $called++; }, 'char:j');

        $action = $bindings->get(new Key('j', Key::TYPE_CHAR));
        $this->assertNotNull($action);
        $action(new Key('j', Key::TYPE_CHAR));
        $this->assertSame(1, $called);
    }

    public function testBindAttachesSameActionToMultiplePatterns(): void
    {
        $bindings = new ClosureKeyBindings();
        $called = 0;
        $bindings->bind(function () use (&$called) { $called++; }, 'char:j', 'escape:[B', 'control:n');

        foreach ([
            new Key('j', Key::TYPE_CHAR),
            new Key("\x1b[B", Key::TYPE_ESCAPE),
            new Key("\x0e", Key::TYPE_CONTROL),
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
        $this->assertNull($bindings->get(new Key('z', Key::TYPE_CHAR)));
    }
}
