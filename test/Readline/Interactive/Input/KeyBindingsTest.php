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

use Psy\Output\Theme;
use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Actions\InsertLineBreakAction;
use Psy\Readline\Interactive\HistorySearch;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Input\KeyBindings;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\OverlayViewport;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class KeyBindingsTest extends TestCase
{
    use BufferAssertionTrait;

    private function createDefaultBindings(?History $history = null): KeyBindings
    {
        $history = $history ?? new History();
        $terminal = $this->createMock(Terminal::class);
        $viewport = new OverlayViewport($terminal);
        $frameRenderer = new FrameRenderer($terminal, $viewport);
        $search = new HistorySearch($terminal, $history, $frameRenderer, $viewport, new Theme());

        return KeyBindings::createDefault($history, $search);
    }

    public function testEnterAndReturnUseFallbackAction(): void
    {
        $bindings = $this->createDefaultBindings();

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new KeyEvent("\n", KeyEvent::TYPE_CHAR))
        );
        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new KeyEvent("\r", KeyEvent::TYPE_CHAR))
        );
    }

    public function testShiftEnterVariantsAreBoundToInsertLineBreak(): void
    {
        $bindings = $this->createDefaultBindings();

        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new KeyEvent("\033[13;2u", KeyEvent::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new KeyEvent("\033[13;2~", KeyEvent::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new KeyEvent("\033[27;2;13~", KeyEvent::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new KeyEvent("\033\r", KeyEvent::TYPE_ESCAPE))
        );
        $this->assertInstanceOf(
            InsertLineBreakAction::class,
            $bindings->get(new KeyEvent("\033\n", KeyEvent::TYPE_ESCAPE))
        );
    }

    public function testRightArrowBindingUsesFallbackAction(): void
    {
        $bindings = $this->createDefaultBindings();

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE))
        );
    }

    public function testRightArrowAcceptsSuggestionWhenAvailable(): void
    {
        $bindings = $this->createDefaultBindings();
        $action = $bindings->get(new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE));
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
        $bindings = $this->createDefaultBindings();
        $action = $bindings->get(new KeyEvent("\033[C", KeyEvent::TYPE_ESCAPE));
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
        $bindings = $this->createDefaultBindings();
        $action = $bindings->get(new KeyEvent("\n", KeyEvent::TYPE_CHAR));
        $this->assertNotNull($action);

        $buffer = new Buffer();
        $buffer->setText('echo 1');

        $terminal = $this->createMock(Terminal::class);
        $terminal->expects($this->once())
            ->method('write')
            ->with("\n\n\n");

        $readline = $this->createMock(Readline::class);
        $readline->method('getInputFrameOuterRowCount')->willReturn(2);

        $this->assertFalse($action->execute($buffer, $terminal, $readline));
    }

    public function testControlDBindingUsesFallbackAction(): void
    {
        $bindings = $this->createDefaultBindings();

        $this->assertInstanceOf(
            FallbackAction::class,
            $bindings->get(new KeyEvent("\x04", KeyEvent::TYPE_CONTROL))
        );
    }
}
