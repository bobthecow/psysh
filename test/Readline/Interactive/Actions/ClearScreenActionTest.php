<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Actions;

use Psy\Readline\Interactive\Actions\ClearScreenAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class ClearScreenActionTest extends TestCase
{
    public function testClearScreenClearsPreviousLinesAndInvalidatesFrame(): void
    {
        $buffer = new Buffer();
        $buffer->insert('echo "test"');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);

        $readline->expects($this->once())
            ->method('clearPreviousLines');
        $terminal->expects($this->once())
            ->method('write')
            ->with("\033[H\033[2J");
        $terminal->expects($this->once())
            ->method('invalidateFrame')
            ->with(true);

        $action = new ClearScreenAction();
        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertSame('echo "test"', $buffer->getText());
    }

    public function testGetName(): void
    {
        $action = new ClearScreenAction();

        $this->assertSame('clear-screen', $action->getName());
    }
}
