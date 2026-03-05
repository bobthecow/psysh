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

use Psy\Exception\BreakException;
use Psy\Readline\Interactive\Actions\DeleteForwardAction;
use Psy\Readline\Interactive\Actions\ExitIfEmptyAction;
use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class ExitOrDeleteActionTest extends TestCase
{
    use BufferAssertionTrait;

    private function createCtrlDAction(): FallbackAction
    {
        return new FallbackAction([
            new ExitIfEmptyAction(),
            new DeleteForwardAction(),
        ]);
    }

    public function testExitOnEmptyBuffer(): void
    {
        $buffer = new Buffer();
        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);

        $action = $this->createCtrlDAction();

        $this->expectException(BreakException::class);
        $action->execute($buffer, $terminal, $readline);
    }

    public function testDeleteCharWithInput(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'te<cursor>st');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $action = $this->createCtrlDAction();

        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertBufferState('te<cursor>t', $buffer); // 's' deleted
    }

    public function testDeleteAtEndOfBuffer(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'test<cursor>');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $action = $this->createCtrlDAction();

        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertBufferState('test<cursor>', $buffer); // nothing to delete
    }

    public function testDeleteAtStartOfBuffer(): void
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>test');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $action = $this->createCtrlDAction();

        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>est', $buffer); // 't' deleted
    }
}
