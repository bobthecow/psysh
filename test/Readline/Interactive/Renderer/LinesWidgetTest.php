<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Renderer;

use Psy\Readline\Interactive\Renderer\Area;
use Psy\Readline\Interactive\Renderer\Frame;
use Psy\Readline\Interactive\Renderer\LinesWidget;
use Psy\Test\TestCase;

class LinesWidgetTest extends TestCase
{
    public function testRenderAppendsLinesAndReturnsCount(): void
    {
        $frame = new Frame(['existing'], 0, 0);
        $widget = new LinesWidget(['one', 'two', 'three']);

        $consumed = $widget->render($frame, new Area(80, 10));

        $this->assertSame(3, $consumed);
        $this->assertSame(['existing', 'one', 'two', 'three'], $frame->getLines());
    }

    public function testRenderHonorsAreaHeightBudget(): void
    {
        $frame = new Frame([], 0, 0);
        $widget = new LinesWidget(['one', 'two', 'three', 'four']);

        $consumed = $widget->render($frame, new Area(80, 2));

        $this->assertSame(2, $consumed);
        $this->assertSame(['one', 'two'], $frame->getLines());
    }

    public function testRenderEmptyListConsumesNoRows(): void
    {
        $frame = new Frame([], 0, 0);
        $widget = new LinesWidget([]);

        $consumed = $widget->render($frame, new Area(80, 10));

        $this->assertSame(0, $consumed);
        $this->assertSame([], $frame->getLines());
    }
}
