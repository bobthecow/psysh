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
use Psy\Readline\Interactive\Actions\DedentLeadingIndentationAction;
use Psy\Readline\Interactive\Actions\DeleteBackwardCharAction;
use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class DeleteBackwardActionTest extends TestCase
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

    private function createBackspaceAction(): FallbackAction
    {
        return new FallbackAction([
            new DedentLeadingIndentationAction(),
            new DeleteBackwardCharAction(),
        ]);
    }

    public function testBackspaceAtIndentedLineStartDedentsInMultilineMode(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n<cursor>    bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n<cursor>bar", $this->buffer);
    }

    public function testBackspaceAtIndentBoundaryDedentsOneLevelInMultilineMode(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n    <cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n<cursor>bar", $this->buffer);
    }

    public function testBackspaceDedentsOnlyOneLevelWhenLineHasMultipleIndentLevels(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n        <cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n    <cursor>bar", $this->buffer);
    }

    public function testBackspaceFromFiveSpacesDedentsToNearestTabStopThenToLineStart(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n     <cursor>bar");

        $this->readline->expects($this->exactly(2))
            ->method('isMultilineMode')
            ->willReturn(true);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n    <cursor>bar", $this->buffer);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n<cursor>bar", $this->buffer);
    }

    public function testBackspaceKeepsDefaultBehaviorOutsideMultilineMode(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n    <cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(false);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("foo(\n   <cursor>bar", $this->buffer);
    }

    public function testBackspaceAtNonIndentedLineStartStillJoinsLinesInMultilineMode(): void
    {
        $action = $this->createBackspaceAction();
        $this->setBufferState($this->buffer, "foo(\n<cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);

        $this->assertTrue($action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState('foo(<cursor>bar', $this->buffer);
    }
}
