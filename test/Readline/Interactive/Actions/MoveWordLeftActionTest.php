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

use Psy\Readline\Interactive\Actions\MoveWordLeftAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class MoveWordLeftActionTest extends TestCase
{
    use BufferAssertionTrait;

    private $terminal;
    private $readline;
    private $action;

    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
        $this->action = new MoveWordLeftAction();
    }

    public function testGetName()
    {
        $this->assertSame('move-word-left', $this->action->getName());
    }

    public function testMoveWordLeftFromEnd()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world test<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello world <cursor>test', $buffer);
    }

    public function testMoveWordLeftFromMiddle()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello worl<cursor>d test');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello <cursor>world test', $buffer);
    }

    public function testMoveWordLeftMultipleTimes()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'one two three four<cursor>');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one two three <cursor>four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one two <cursor>three four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one <cursor>two three four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('<cursor>one two three four', $buffer);
    }

    public function testMoveWordLeftAtStart()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>hello world', $buffer); // Stays at start
    }

    public function testMoveWordLeftEmptyBuffer()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>', $buffer);
    }

    public function testMoveWordLeftWithPhpOperators()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '$var->method()->chain()<cursor>');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var->method()-><cursor>chain()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var-><cursor>method()->chain()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$<cursor>var->method()->chain()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('<cursor>$var->method()->chain()', $buffer);
    }

    public function testMoveWordLeftWithNamespace()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'Psy\\Readline\\Enhanced\\Buffer<cursor>');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline\\Enhanced\\<cursor>Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline\\<cursor>Enhanced\\Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\<cursor>Readline\\Enhanced\\Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('<cursor>Psy\\Readline\\Enhanced\\Buffer', $buffer);
    }

    public function testMoveWordLeftWithStaticCall()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'ClassName::staticMethod()<cursor>');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('ClassName::<cursor>staticMethod()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('<cursor>ClassName::staticMethod()', $buffer);
    }

    public function testMoveWordLeftWithMultipleSpaces()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello    world<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello    <cursor>world', $buffer);
    }

    public function testMoveWordLeftWithLeadingSpaces()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '    hello world<cursor>');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('    hello <cursor>world', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('    <cursor>hello world', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('<cursor>    hello world', $buffer);
    }

    public function testMoveWordLeftReturnsTrue()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
    }
}
