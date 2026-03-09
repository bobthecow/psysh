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

use Psy\Readline\Interactive\Actions\DedentLeadingIndentationAction;
use Psy\Readline\Interactive\Actions\DeleteBackwardCharAction;
use Psy\Readline\Interactive\Actions\DeleteBracketPairAction;
use Psy\Readline\Interactive\Actions\FallbackAction;
use Psy\Readline\Interactive\Actions\InsertCloseBracketAction;
use Psy\Readline\Interactive\Actions\InsertOpenBracketAction;
use Psy\Readline\Interactive\Actions\InsertQuoteAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class BracketActionsTest extends TestCase
{
    use BufferAssertionTrait;

    private Buffer $buffer;
    private Terminal $terminal;
    private Readline $readline;

    protected function setUp(): void
    {
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
        $this->readline->method('isMultilineMode')->willReturn(false);
    }

    private function createBackspaceAction(bool $smartBrackets): FallbackAction
    {
        if ($smartBrackets) {
            return new FallbackAction([
                new DedentLeadingIndentationAction(),
                new DeleteBracketPairAction(),
                new DeleteBackwardCharAction(),
            ]);
        }

        return new FallbackAction([
            new DedentLeadingIndentationAction(),
            new DeleteBackwardCharAction(),
        ]);
    }

    public function testInsertOpenBracketAutoCloses()
    {
        $action = new InsertOpenBracketAction('(');
        $this->setBufferState($this->buffer, 'test<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test(<cursor>)', $this->buffer);
    }

    public function testInsertOpenBracketNoAutoCloseBeforeAlphanumeric()
    {
        $action = new InsertOpenBracketAction('(');
        $this->setBufferState($this->buffer, 'ar<cursor>ray');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('ar(<cursor>ray', $this->buffer);
    }

    public function testInsertCloseBracketSkipsOver()
    {
        $action = new InsertCloseBracketAction(')');
        $this->setBufferState($this->buffer, 'test(<cursor>)');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test()<cursor>', $this->buffer);
    }

    public function testInsertCloseBracketInsertsWhenNoMatch()
    {
        $action = new InsertCloseBracketAction(')');
        $this->setBufferState($this->buffer, 'test<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test)<cursor>', $this->buffer);
    }

    public function testInsertQuoteAutoCloses()
    {
        $action = new InsertQuoteAction('"');
        $this->setBufferState($this->buffer, 'echo <cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo "<cursor>"', $this->buffer);
    }

    public function testInsertQuoteSkipsOver()
    {
        $action = new InsertQuoteAction('"');
        $this->setBufferState($this->buffer, 'echo "<cursor>"');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('echo ""<cursor>', $this->buffer);
    }

    public function testInsertQuoteNoAutoCloseInsideString()
    {
        $action = new InsertQuoteAction('"');
        $this->setBufferState($this->buffer, '"hello <cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('"hello "<cursor>', $this->buffer);
    }

    public function testDeleteBackwardDeletesPair()
    {
        $action = $this->createBackspaceAction(true);
        $this->setBufferState($this->buffer, 'test(<cursor>)');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test<cursor>', $this->buffer);
    }

    public function testDeleteBackwardNormalWhenNoPair()
    {
        $action = $this->createBackspaceAction(true);
        $this->setBufferState($this->buffer, 'test)<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test<cursor>', $this->buffer);
    }

    public function testDeleteBackwardWithoutHandler()
    {
        $action = $this->createBackspaceAction(false);
        $this->setBufferState($this->buffer, 'test(<cursor>)');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test<cursor>)', $this->buffer);
    }

    public function testAllBracketTypes()
    {
        $brackets = [
            '(' => ')',
            '[' => ']',
            '{' => '}',
        ];

        foreach ($brackets as $open => $close) {
            $this->buffer->clear();
            $this->setBufferState($this->buffer, 'test<cursor>');

            $action = new InsertOpenBracketAction($open);
            $action->execute($this->buffer, $this->terminal, $this->readline);

            $this->assertBufferState("test{$open}<cursor>{$close}", $this->buffer);
        }
    }

    public function testInsertCloseBracketDedentsWhenOnlyWhitespace()
    {
        $action = new InsertCloseBracketAction(')');
        $this->setBufferState($this->buffer, "foo(\n    <cursor>)");

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState("foo(\n)<cursor>", $this->buffer);
    }

    public function testInsertCloseBracketNoDedentwhenTextBeforeCursor()
    {
        $action = new InsertCloseBracketAction(')');
        $this->setBufferState($this->buffer, "foo(\n    bar<cursor>)");

        $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState("foo(\n    bar)<cursor>", $this->buffer);
    }

    public function testInsertCloseBracketDedentsForAllBracketTypes()
    {
        $brackets = [
            '(' => ')',
            '[' => ']',
            '{' => '}',
        ];

        foreach ($brackets as $open => $close) {
            $this->buffer->clear();
            $this->setBufferState($this->buffer, "test{$open}\n    <cursor>{$close}");

            $action = new InsertCloseBracketAction($close);
            $action->execute($this->buffer, $this->terminal, $this->readline);

            $this->assertBufferState("test{$open}\n{$close}<cursor>", $this->buffer);
        }
    }
}
