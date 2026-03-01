<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Input;

use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Actions\InsertLineBreakAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\Key;
use Psy\Readline\Interactive\Input\KeyBindings;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class KeyBindingsTest extends TestCase
{
    use BufferAssertionTrait;

    public function testEnterAndReturnUseFallbackAction(): void
    {
        $bindings = KeyBindings::createDefault(new History());

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new Key("\n", Key::TYPE_CHAR))
        );
        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new Key("\r", Key::TYPE_CHAR))
        );
    }

    public function testShiftEnterVariantsAreBoundToInsertLineBreak(): void
    {
        $bindings = KeyBindings::createDefault(new History());

        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new Key("\033[13;2u", Key::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new Key("\033[13;2~", Key::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new Key("\033[27;2;13~", Key::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new Key("\033\r", Key::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new Key("\033\n", Key::TYPE_ESCAPE))
        );
    }

    public function testRightArrowBindingUsesFallbackAction(): void
    {
        $bindings = KeyBindings::createDefault(new History());

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new Key("\033[C", Key::TYPE_ESCAPE))
        );
    }

    public function testRightArrowAcceptsSuggestionWhenAvailable(): void
    {
        $bindings = KeyBindings::createDefault(new History());
        $action = $bindings->get(new Key("\033[C", Key::TYPE_ESCAPE));
        $this->assertNotNull($action);

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'foo<cursor>');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $suggestion = SuggestionResult::forAppend('Bar', SuggestionResult::SOURCE_HISTORY, 3);
        $readline->expects($this->once())
            ->method('clearSuggestion');
        $readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn($suggestion);

        $this->assertTrue($action->execute($buffer, $terminal, $readline));
        $this->assertBufferState('fooBar<cursor>', $buffer);
    }

    public function testRightArrowFallsBackToMoveRightWhenNoSuggestion(): void
    {
        $bindings = KeyBindings::createDefault(new History());
        $action = $bindings->get(new Key("\033[C", Key::TYPE_ESCAPE));
        $this->assertNotNull($action);

        $buffer = new Buffer();
        $this->setBufferState($buffer, 'f<cursor>oo');

        $terminal = $this->createMock(Terminal::class);
        $readline = $this->createMock(Readline::class);
        $readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn(null);
        $readline->expects($this->never())
            ->method('clearSuggestion');

        $this->assertTrue($action->execute($buffer, $terminal, $readline));
        $this->assertBufferState('fo<cursor>o', $buffer);
    }

    public function testEnterActionCanSubmitLine(): void
    {
        $bindings = KeyBindings::createDefault(new History());
        $action = $bindings->get(new Key("\n", Key::TYPE_CHAR));
        $this->assertNotNull($action);

        $buffer = new Buffer();
        $buffer->setText('echo 1');

        $terminal = $this->createMock(Terminal::class);
        $terminal->expects($this->once())
            ->method('write')
            ->with("\n\n\n");

        $readline = $this->createMock(Readline::class);
        $readline->method('getInputFrameOuterRowCount')->willReturn(2);
        $readline->method('isMultilineMode')
            ->willReturn(false);
        $readline->expects($this->once())
            ->method('isCommand')
            ->with('echo 1')
            ->willReturn(false);

        $this->assertFalse($action->execute($buffer, $terminal, $readline));
    }

    public function testControlDBindingUsesFallbackAction(): void
    {
        $bindings = KeyBindings::createDefault(new History());

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new Key("\x04", Key::TYPE_CONTROL))
        );
    }
}
