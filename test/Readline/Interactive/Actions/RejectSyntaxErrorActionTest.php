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
use Psy\Readline\Interactive\Actions\RejectSyntaxErrorAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class RejectSyntaxErrorActionTest extends TestCase
{
    use BufferAssertionTrait;

    private RejectSyntaxErrorAction $action;
    private Buffer $buffer;

    /** @var Terminal&MockObject */
    private Terminal $terminal;

    /** @var Readline&MockObject */
    private Readline $readline;

    protected function setUp(): void
    {
        $this->action = new RejectSyntaxErrorAction();
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
    }

    public function testRejectsExtraClosingParen(): void
    {
        $this->setBufferState($this->buffer, 'var_dump(1))<cursor>');

        $this->readline->expects($this->once())->method('setInputFrameError')->with(true);
        $this->terminal->expects($this->once())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        // Buffer unchanged
        $this->assertBufferState('var_dump(1))<cursor>', $this->buffer);
    }

    public function testRejectsExtraClosingBracket(): void
    {
        $this->setBufferState($this->buffer, '[1, 2]]<cursor>');

        $this->readline->expects($this->once())->method('setInputFrameError')->with(true);
        $this->terminal->expects($this->once())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
    }

    public function testPassesThroughValidCode(): void
    {
        $this->setBufferState($this->buffer, 'echo "hello"<cursor>');

        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testPassesThroughIncompleteArray(): void
    {
        $this->setBufferState($this->buffer, '$x = [1, 2<cursor>');

        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testPassesThroughEmptyBuffer(): void
    {
        $this->setBufferState($this->buffer, '<cursor>');

        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testPassesThroughUnterminatedString(): void
    {
        $this->setBufferState($this->buffer, 'echo "hello<cursor>');

        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testPassesThroughControlStructureWithoutBody(): void
    {
        $this->setBufferState($this->buffer, 'if ($x)<cursor>');

        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testPassesThroughKnownCommandWithArguments(): void
    {
        $this->setBufferState($this->buffer, 'help ls<cursor>');

        $this->readline->expects($this->once())->method('isCommand')->with('help ls')->willReturn(true);
        $this->readline->expects($this->once())->method('isInOpenStringOrComment')->with('help ls')->willReturn(false);
        $this->readline->expects($this->never())->method('setInputFrameError');
        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }
}
