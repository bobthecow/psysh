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

use Psy\Readline\Interactive\Actions\MoveWordRightAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class MoveWordRightActionTest extends TestCase
{
    use BufferAssertionTrait;

    private $terminal;
    private $readline;
    private $action;

    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
        $this->action = new MoveWordRightAction();
    }

    public function testGetName()
    {
        $this->assertSame('move-word-right', $this->action->getName());
    }

    public function testMoveWordRightFromStart()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world test');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello<cursor> world test', $buffer);
    }

    public function testMoveWordRightFromMiddle()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hel<cursor>lo world test');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello<cursor> world test', $buffer);
    }

    public function testMoveWordRightMultipleTimes()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>one two three four');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one<cursor> two three four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one two<cursor> three four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one two three<cursor> four', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('one two three four<cursor>', $buffer);
    }

    public function testMoveWordRightAtEnd()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, 'hello world<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello world<cursor>', $buffer); // Stays at end
    }

    public function testMoveWordRightEmptyBuffer()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('<cursor>', $buffer);
    }

    public function testMoveWordRightWithPhpOperators()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$var->method()->chain()');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var<cursor>->method()->chain()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var->method<cursor>()->chain()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var->method()->chain<cursor>()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$var->method()->chain()<cursor>', $buffer);
    }

    public function testMoveWordRightWithNamespace()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>Psy\\Readline\\Enhanced\\Buffer');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy<cursor>\\Readline\\Enhanced\\Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline<cursor>\\Enhanced\\Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline\\Enhanced<cursor>\\Buffer', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline\\Enhanced\\Buffer<cursor>', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('Psy\\Readline\\Enhanced\\Buffer<cursor>', $buffer);
    }

    public function testMoveWordRightWithStaticCall()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>ClassName::staticMethod()');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('ClassName<cursor>::staticMethod()', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('ClassName::staticMethod<cursor>()', $buffer);
    }

    public function testMoveWordRightWithMultipleSpaces()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello    world');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState('hello<cursor>    world', $buffer);
    }

    public function testMoveWordRightWithLeadingSpaces()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>    hello world');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('    hello<cursor> world', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('    hello world<cursor>', $buffer);
    }

    public function testMoveWordRightWithTrailingSpaces()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world    ');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('hello<cursor> world    ', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('hello world<cursor>    ', $buffer);
    }

    public function testMoveWordRightWithMixedContent()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>$foo = Bar::baz($qux);');

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$foo<cursor> = Bar::baz($qux);', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$foo = Bar<cursor>::baz($qux);', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$foo = Bar::baz<cursor>($qux);', $buffer);

        $this->action->execute($buffer, $this->terminal, $this->readline);
        $this->assertBufferState('$foo = Bar::baz($qux<cursor>);', $buffer);
    }

    public function testMoveWordRightReturnsTrue()
    {
        $buffer = new Buffer();
        $this->setBufferState($buffer, '<cursor>hello world');

        $result = $this->action->execute($buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
    }
}
