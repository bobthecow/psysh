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
use Psy\Completion\CompletionEngine;
use Psy\Completion\Source\ObjectMethodSource;
use Psy\Completion\Source\VariableSource;
use Psy\Context;
use Psy\Readline\Interactive\Actions\InsertIndentOnTabAction;
use Psy\Readline\Interactive\Actions\TabAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;

class TabActionTest extends TestCase
{
    use BufferAssertionTrait;

    private TabAction $action;
    private Buffer $buffer;

    /** @var Terminal&MockObject */
    private Terminal $terminal;

    /** @var Readline&MockObject */
    private Readline $readline;
    private CompletionEngine $completer;
    private Context $context;

    protected function setUp(): void
    {
        if (!\class_exists('Symfony\Component\Console\Cursor')) {
            $this->markTestSkipped('Interactive readline requires Symfony Console 5.1+');
        }

        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);

        $this->context = new Context();
        $this->completer = new CompletionEngine($this->context);
        $this->completer->addSource(new VariableSource($this->context));
        $this->completer->addSource(new ObjectMethodSource());

        $this->action = new TabAction($this->completer);

        $this->terminal->method('getWidth')->willReturn(80);
        $this->terminal->method('getFormatter')->willReturn(new OutputFormatter());
        $this->readline->method('getOverlayAvailableRows')->willReturn(20);
        $this->readline->method('renderOverlay');
        $this->readline->method('enterMenuMode');
        $this->readline->method('exitMenuMode');
    }

    public function testNoCompleterRingsBell()
    {
        $action = new TabAction(null);
        $this->setBufferState($this->buffer, 'test<cursor>');

        $this->terminal->expects($this->once())->method('bell');

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('test<cursor>', $this->buffer);
    }

    public function testSingleMatchCompletion()
    {
        $this->context->setAll([
            'testVariable' => 'value',
        ]);

        $this->setBufferState($this->buffer, '$testVar<cursor>');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('$testVariable<cursor>', $this->buffer);
    }

    public function testMultipleMatchesShowOptions()
    {
        $this->context->setAll([
            'test1' => 'value1',
            'test2' => 'value2',
            'test3' => 'value3',
        ]);

        $this->setBufferState($this->buffer, '$test<cursor>');

        $this->readline->expects($this->once())
            ->method('renderOverlay');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);
    }

    public function testNoMatchesSwallowsTab()
    {
        $this->setBufferState($this->buffer, 'nomatch<cursor>');

        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('nomatch<cursor>', $this->buffer);
    }

    public function testEmptyPromptSwallowsTab()
    {
        $this->setBufferState($this->buffer, '<cursor>');

        $this->terminal->expects($this->never())->method('bell');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('<cursor>', $this->buffer);
    }

    public function testTabAtStartOfMultilineLineInsertsIndent(): void
    {
        $action = new InsertIndentOnTabAction();
        $this->setBufferState($this->buffer, "foo(\n<cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);
        $this->readline->expects($this->never())
            ->method('renderOverlay');

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("foo(\n    <cursor>bar", $this->buffer);
    }

    public function testTabInLeadingWhitespaceUsesNextTabStop(): void
    {
        $action = new InsertIndentOnTabAction();
        $this->setBufferState($this->buffer, "foo(\n     <cursor>bar");

        $this->readline->expects($this->once())
            ->method('isMultilineMode')
            ->willReturn(true);
        $this->readline->expects($this->never())
            ->method('renderOverlay');

        $result = $action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("foo(\n        <cursor>bar", $this->buffer);
    }

    public function testCompletionInMiddleOfLine()
    {
        $this->context->setAll([
            'variable' => 'value',
        ]);

        $this->setBufferState($this->buffer, '$var<cursor> . "test"');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('$variable<cursor> . "test"', $this->buffer);
    }

    public function testMultilineCompletion()
    {
        $this->context->setAll([
            'multilineVar' => 'value',
        ]);

        $this->setBufferState($this->buffer, "echo 'first line';\n\$multi<cursor>");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState("echo 'first line';\n\$multilineVar<cursor>", $this->buffer);
    }

    public function testMultilineCompletionSetsBufferCorrectly(): void
    {
        $this->context->setAll([
            'multilineVar' => 'value',
        ]);

        $this->setBufferState($this->buffer, "echo 'first line';\n\$multi<cursor>");

        $this->assertTrue($this->action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertBufferState("echo 'first line';\n\$multilineVar<cursor>", $this->buffer);
    }

    public function testMultilineObjectAccessCompletionUsesFullBuffer()
    {
        $this->context->setAll([
            'foo' => new \DateTime(),
        ]);

        $this->setBufferState($this->buffer, "\$foo\n->forma<cursor>");

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertTrue($result);
        $this->assertBufferState("\$foo\n->format<cursor>", $this->buffer);
    }

    public function testCommonPrefixInsertion()
    {
        $this->context->setAll([
            'testVariable1' => 'value1',
            'testVariable2' => 'value2',
            'testVariable3' => 'value3',
        ]);

        $this->setBufferState($this->buffer, '$testVar<cursor>');

        $this->readline->expects($this->once())
            ->method('renderOverlay');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('$testVariable<cursor>', $this->buffer);
    }

    public function testSmartBracketsFunctionWithParams()
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['array_merge']);

        $action = new TabAction($completer, true);
        $action->setInteractiveSelectionEnabled(false);
        $this->setBufferState($this->buffer, 'array_merg<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        // Cursor inside parens for functions that accept parameters
        $this->assertBufferState('array_merge(<cursor>)', $this->buffer);
    }

    public function testSmartBracketsFunctionWithNoParams()
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['getcwd']);

        $action = new TabAction($completer, true);
        $action->setInteractiveSelectionEnabled(false);
        $this->setBufferState($this->buffer, 'getc<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        // Cursor after parens for zero-arg functions
        $this->assertBufferState('getcwd()<cursor>', $this->buffer);
    }

    public function testSmartBracketsFunctionWithOnlyOptionalParams()
    {
        $completer = $this->createMock(CompletionEngine::class);
        $completer->method('getCompletions')->willReturn(['error_reporting']);

        $action = new TabAction($completer, true);
        $action->setInteractiveSelectionEnabled(false);
        $this->setBufferState($this->buffer, 'error_repo<cursor>');

        $action->execute($this->buffer, $this->terminal, $this->readline);

        // Cursor inside parens even for only-optional-params functions
        $this->assertBufferState('error_reporting(<cursor>)', $this->buffer);
    }

    public function testResetCompletionOnContextChange()
    {
        $this->context->setAll([
            'test1' => 'value1',
            'test2' => 'value2',
        ]);

        $this->setBufferState($this->buffer, '$test<cursor>');
        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->setBufferState($this->buffer, '$test1<cursor>');
        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('$test1<cursor>', $this->buffer);
    }

    public function testSingleMatchCompletionAfterMultibyteText()
    {
        $this->context->setAll([
            'testVariable' => 'value',
        ]);

        $this->setBufferState($this->buffer, 'é $testVar<cursor>');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('é $testVariable<cursor>', $this->buffer);
    }

    public function testSingleMatchCompletionAfterEmoji()
    {
        $this->context->setAll([
            'testVariable' => 'value',
        ]);

        $this->setBufferState($this->buffer, '👍 $testVar<cursor>');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('👍 $testVariable<cursor>', $this->buffer);
    }

    public function testCompletionInMiddleOfLineAfterMultibyte()
    {
        $this->context->setAll([
            'variable' => 'value',
        ]);

        $this->setBufferState($this->buffer, 'é $var<cursor> . "test"');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        $this->assertBufferState('é $variable<cursor> . "test"', $this->buffer);
    }

    public function testBackspaceToEmptyExitsInteractiveMode()
    {
        $this->context->setAll([
            'test1' => 'value1',
            'test2' => 'value2',
            'test3' => 'value3',
        ]);

        $this->setBufferState($this->buffer, '$te<cursor>');

        $result = $this->action->execute($this->buffer, $this->terminal, $this->readline);

        // Note: In actual interactive mode with a TTY, the user would now be able to:
        // 1. Type characters to filter
        // 2. Backspace until filter is empty
        // 3. Interactive mode should exit, returning control to terminal
        //
        // This test verifies the basic setup. The backspace-to-exit logic
        // is in handleInteractiveSelection() which requires a TTY to test fully.
        // We're documenting the expected behavior here.
        $this->assertTrue($result);
    }
}
