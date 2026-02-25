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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\Actions\InsertLineBreakAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class InsertLineBreakActionTest extends TestCase
{
    use BufferAssertionTrait;

    private Buffer $buffer;

    /** @var Terminal&MockObject */
    private Terminal $terminal;

    /** @var Readline&MockObject */
    private Readline $readline;

    protected function setUp(): void
    {
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
    }

    public function testInsertLineBreakEntersMultilineAndAppliesIndentation(): void
    {
        $action = new InsertLineBreakAction();

        $this->setBufferState($this->buffer, 'foo(<cursor>)');

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(false);
        $this->readline->expects($this->once())
            ->method('enterMultilineMode');

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n    <cursor>)", $this->buffer);
    }

    public function testInsertLineBreakDoesNotReenterMultilineMode(): void
    {
        $action = new InsertLineBreakAction();

        $this->setBufferState($this->buffer, "echo 'x'<cursor>");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);
        $this->readline->expects($this->never())
            ->method('enterMultilineMode');

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("echo 'x'\n<cursor>", $this->buffer);
    }
}
