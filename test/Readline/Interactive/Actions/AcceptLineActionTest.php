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
use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Actions\InsertLineBreakOnIncompleteStatementAction;
use Psy\Readline\Interactive\Actions\InsertLineBreakOnUnclosedBracketsAction;
use Psy\Readline\Interactive\Actions\RejectSyntaxErrorAction;
use Psy\Readline\Interactive\Actions\SubmitLineAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class AcceptLineActionTest extends TestCase
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

    private function createEnterAction(bool $smartBrackets): FallbackAction
    {
        return new FallbackAction([
            new RejectSyntaxErrorAction(),
            new InsertLineBreakOnUnclosedBracketsAction($smartBrackets),
            new InsertLineBreakOnIncompleteStatementAction(),
            new SubmitLineAction(),
        ], false);
    }

    public function testEnterBetweenParensSubmitsWhenComplete()
    {
        $action = $this->createEnterAction(true);

        // foo() is a complete statement, so Enter submits even with
        // cursor between the parens.
        $this->setBufferState($this->buffer, 'foo(<cursor>)');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(false);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterBetweenBracesSubmitsWhenComplete()
    {
        $action = $this->createEnterAction(true);

        // function() {} is a complete expression, so Enter submits.
        $this->setBufferState($this->buffer, 'function() {<cursor>}');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(false);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterBetweenParensInsertsNewlineWhenIncomplete()
    {
        $action = $this->createEnterAction(true);

        // $fn = function(<cursor>) is incomplete (no body yet), so Enter
        // inserts a newline inside the parens.
        $this->setBufferState($this->buffer, '$fn = function(<cursor>)');

        $this->readline->method('isMultilineMode')->willReturn(false);

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("\$fn = function(\n    <cursor>\n)", $this->buffer);
    }

    public function testEnterAfterBracketsExecutes()
    {
        $action = $this->createEnterAction(true);

        $this->setBufferState($this->buffer, 'foo()<cursor>');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(false);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterWithoutHandlerWorksNormally()
    {
        $action = $this->createEnterAction(false);

        $this->setBufferState($this->buffer, 'foo(<cursor>)');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(false);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterUsesCompactFrameFooterSpacingWhenEnabled(): void
    {
        $action = $this->createEnterAction(true);

        $this->setBufferState($this->buffer, 'echo 1<cursor>');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(false);
        $this->readline->expects($this->any())->method('getInputFrameOuterRowCount')->willReturn(0);
        $this->terminal->expects($this->once())->method('write')->with("\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterInMultilineMovesBelowLowerSpacingRows(): void
    {
        $action = $this->createEnterAction(true);

        $this->setBufferState($this->buffer, '<cursor>echo 1;'."\n".'echo 2;');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isInOpenStringOrComment')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(true);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        // Two-line input, cursor on first line: remaining lines (2) + lower
        // dark spacer + lower plain gutter = 4 newlines.
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterBetweenBracketsInMultilineModeSubmitsWhenComplete()
    {
        $action = $this->createEnterAction(true);

        // function test() {} is a complete declaration, so Enter submits
        // even in multiline mode.
        $this->setBufferState($this->buffer, 'function test() {<cursor>}');

        $this->readline->method('isCommand')->willReturn(false);
        $this->readline->method('isInOpenStringOrComment')->willReturn(false);
        $this->readline->method('isMultilineMode')->willReturn(true);
        $this->readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $this->terminal->expects($this->once())->method('write')->with("\n\n\n");

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertFalse($result);
    }

    public function testEnterBetweenBracketsInMultilineModeInsertsNewlineWhenIncomplete()
    {
        $action = $this->createEnterAction(true);

        // $fn = function(<cursor>) is incomplete (no body), so Enter
        // still inserts a newline even in multiline mode.
        $this->setBufferState($this->buffer, '$fn = function(<cursor>)');

        $this->readline->method('isMultilineMode')->willReturn(true);

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("\$fn = function(\n    <cursor>\n)", $this->buffer);
    }
}
