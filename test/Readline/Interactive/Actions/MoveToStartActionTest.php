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

use Psy\Readline\Interactive\Actions\MoveToStartAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class MoveToStartActionTest extends TestCase
{
    use BufferAssertionTrait;

    private $action;
    private $buffer;
    private $terminal;
    private $readline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MoveToStartAction();
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
    }

    public function testSingleLine()
    {
        $this->setBufferState($this->buffer, 'This is a <cursor>single line');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>This is a single line', $this->buffer);
    }

    public function testMultiLineFirstLine()
    {
        $this->setBufferState($this->buffer, "First<cursor> line\nSecond line\nThird line");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("<cursor>First line\nSecond line\nThird line", $this->buffer);
    }

    public function testMultiLineMiddleLine()
    {
        $this->setBufferState($this->buffer, "First line\nSeco<cursor>nd line\nThird line");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("First line\n<cursor>Second line\nThird line", $this->buffer);
    }

    public function testMultiLineLastLine()
    {
        $this->setBufferState($this->buffer, "First line\nSecond line\nThird<cursor> line");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("First line\nSecond line\n<cursor>Third line", $this->buffer);
    }

    public function testGetName()
    {
        $this->assertSame('move-to-start', $this->action->getName());
    }

    public function testSingleLineWithIndentTogglesToIndentThenColumnZero()
    {
        $this->setBufferState($this->buffer, '    indented<cursor> line');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('    <cursor>indented line', $this->buffer);

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>    indented line', $this->buffer);
    }

    public function testSingleLineWithIndentTogglesBackToIndentFromColumnZero()
    {
        $this->setBufferState($this->buffer, '<cursor>    indented line');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('    <cursor>indented line', $this->buffer);
    }

    public function testWhitespaceOnlyLineMovesToColumnZero()
    {
        $this->setBufferState($this->buffer, "first\n    <cursor>  \nthird");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("first\n<cursor>      \nthird", $this->buffer);
    }
}
