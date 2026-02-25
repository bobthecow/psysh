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

use Psy\Readline\Interactive\Actions\ClearBufferAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class ClearBufferActionTest extends TestCase
{
    public function testClearBufferWithInput(): void
    {
        $buffer = new Buffer();
        $buffer->insert('echo "test"');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $terminal->expects($this->never())->method('bell');

        $action = new ClearBufferAction();
        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertTrue($buffer->isEmpty());
    }

    public function testBeepOnEmptyBuffer(): void
    {
        $buffer = new Buffer();

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $terminal->expects($this->once())->method('bell');

        $action = new ClearBufferAction();
        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertTrue($buffer->isEmpty());
    }

    public function testExitMultilineMode(): void
    {
        $readline = $this->createMockReadline(true, true);

        $buffer = new Buffer();
        $buffer->insert('if (true) {');

        $terminal = $this->createMock(Terminal::class);

        $action = new ClearBufferAction();

        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertTrue($buffer->isEmpty());
    }

    public function testDoesNotCallCancelWhenNotInMultilineMode(): void
    {
        $readline = $this->createMockReadline(false, false);

        $buffer = new Buffer();
        $buffer->insert('echo "test"');

        $terminal = $this->createMock(Terminal::class);

        $action = new ClearBufferAction();

        $result = $action->execute($buffer, $terminal, $readline);

        $this->assertTrue($result);
        $this->assertTrue($buffer->isEmpty());
    }

    public function testShowHintOnSecondCtrlCOnEmptyLine(): void
    {
        // Reset timer to ensure clean state
        ClearBufferAction::resetTimer();

        // Use same action instance to share static state
        $action = new ClearBufferAction();

        // First Ctrl-C: should beep
        $buffer1 = new Buffer();
        $terminal1 = $this->createMock(Terminal::class);
        $readline1 = $this->createMock(Readline::class);
        $terminal1->expects($this->once())->method('bell');

        $action->execute($buffer1, $terminal1, $readline1);

        // Second Ctrl-C within timeout: should show hint
        $buffer2 = new Buffer();
        $terminal2 = $this->createMock(Terminal::class);
        $readline2 = $this->createMock(Readline::class);

        $terminal2->expects($this->never())->method('bell');
        $terminal2->expects($this->once())->method('clearToEndOfLine');
        $terminal2->expects($this->once())
            ->method('writeFormatted')
            ->with('<whisper>(Press Ctrl-D to exit, or type \'exit\')</whisper>');
        $terminal2->expects($this->exactly(2))->method('write')
            ->withConsecutive(
                ["\r"],
                ["\n"]
            );

        $result = $action->execute($buffer2, $terminal2, $readline2);

        $this->assertTrue($result);

        // Clean up for other tests
        ClearBufferAction::resetTimer();
    }

    public function testGetName(): void
    {
        $action = new ClearBufferAction();
        $this->assertEquals('clear-buffer', $action->getName());
    }

    private function createMockReadline(bool $isMultiline, bool $expectCancel)
    {
        $readline = $this->createMock(Readline::class);
        $readline->method('isMultilineMode')->willReturn($isMultiline);

        if ($expectCancel) {
            $readline->expects($this->once())->method('cancelMultilineMode');
        } else {
            $readline->expects($this->never())->method('cancelMultilineMode');
        }

        return $readline;
    }
}
