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
use Psy\Readline\Interactive\Actions\AcceptSuggestionAction;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Readline\Interactive\Readline;
use Psy\Readline\Interactive\Suggestion\SuggestionResult;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class AcceptSuggestionActionTest extends TestCase
{
    private AcceptSuggestionAction $action;
    private Buffer $buffer;

    /** @var Terminal&MockObject */
    private Terminal $terminal;

    /** @var Readline&MockObject */
    private Readline $readline;

    protected function setUp(): void
    {
        $this->action = new AcceptSuggestionAction();
        $this->buffer = new Buffer();
        $this->terminal = $this->createMock(Terminal::class);
        $this->readline = $this->createMock(Readline::class);
    }

    public function testNoSuggestionReturnsFalse(): void
    {
        $this->readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn(null);
        $this->readline->expects($this->never())
            ->method('clearSuggestion');

        $this->assertFalse($this->action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertSame('', $this->buffer->getText());
    }

    public function testAcceptSuggestionAppliesEditAndClearsSuggestion(): void
    {
        $this->buffer->setText('$col');
        $suggestion = SuggestionResult::forAppend(
            'ors = compact("red", "blue")',
            SuggestionResult::SOURCE_HISTORY,
            \mb_strlen('$col')
        );

        $this->readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn($suggestion);
        $this->readline->expects($this->once())
            ->method('clearSuggestion');

        $this->assertTrue($this->action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertSame('$colors = compact("red", "blue")', $this->buffer->getText());
    }

    public function testAcceptMultilineSuggestionSetsBufferCorrectly(): void
    {
        $this->buffer->setText('function');
        $acceptText = " test() {\n    return 'multi';\n}";
        $fullText = 'function'.$acceptText;
        $suggestion = SuggestionResult::forAppend(
            " test() { return 'multi'; }",
            SuggestionResult::SOURCE_HISTORY,
            \mb_strlen('function'),
            $acceptText
        );

        $this->readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn($suggestion);
        $this->readline->expects($this->once())
            ->method('clearSuggestion');

        $this->assertTrue($this->action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertSame($fullText, $this->buffer->getText());
    }

    public function testCallSignatureAcceptPreservesBufferPrefix(): void
    {
        $bufferText = 'echo array_merge(';
        $signature = '$array1, $array2, ...$arrays';
        $this->buffer->setText($bufferText);
        $suggestion = SuggestionResult::forAppend(
            $signature,
            SuggestionResult::SOURCE_CALL_SIGNATURE,
            \mb_strlen($bufferText),
            $signature.')'
        );

        $this->readline->expects($this->once())
            ->method('getCurrentSuggestion')
            ->willReturn($suggestion);
        $this->readline->expects($this->once())
            ->method('clearSuggestion');

        $this->assertTrue($this->action->execute($this->buffer, $this->terminal, $this->readline));
        $this->assertSame('echo array_merge('.$signature.')', $this->buffer->getText());
    }
}
