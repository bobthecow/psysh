<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive;

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\InteractiveSession;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class InteractiveSessionTest extends TestCase
{
    /**
     * @return Terminal&MockObject
     */
    private function mockTerminal(): Terminal
    {
        return $this->createMock(Terminal::class);
    }

    public function testSetBracketedPasteDoesNotMutateTerminalBeforeStart(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->never())->method('enableBracketedPaste');
        $terminal->expects($this->never())->method('disableBracketedPaste');

        $session = new InteractiveSession($terminal);
        $session->setBracketedPaste(true);
    }

    public function testBracketedPasteConfigurationIsAppliedOnStart(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())->method('enableRawMode')->willReturn(true);
        $terminal->expects($this->once())->method('enableBracketedPaste');
        $terminal->expects($this->once())->method('isBracketedPasteEnabled')->willReturn(true);
        $terminal->expects($this->once())->method('disableBracketedPaste');
        $terminal->expects($this->once())->method('disableRawMode');

        $session = new InteractiveSession($terminal);
        $session->setBracketedPaste(true);
        $session->start();
        $session->stop();
    }

    public function testStartEnablesRawModeOnlyOnce(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())->method('enableRawMode')->willReturn(true);
        $terminal->expects($this->once())->method('isBracketedPasteEnabled')->willReturn(false);
        $terminal->expects($this->once())->method('disableRawMode');

        $session = new InteractiveSession($terminal);
        $session->start();
        $session->start();

        $this->assertTrue($session->isActive());

        $session->stop();
        $this->assertFalse($session->isActive());
    }

    public function testSetBracketedPasteUpdatesTerminalWhileActive(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())->method('enableRawMode')->willReturn(true);
        $terminal->expects($this->once())->method('enableBracketedPaste');
        $terminal->expects($this->once())->method('disableBracketedPaste');
        $terminal->expects($this->once())->method('isBracketedPasteEnabled')->willReturn(false);
        $terminal->expects($this->once())->method('disableRawMode');

        $session = new InteractiveSession($terminal);
        $session->start();
        $session->setBracketedPaste(true);
        $session->setBracketedPaste(false);
        $session->stop();
    }

    public function testStopTeardownIsIdempotent(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())->method('enableRawMode')->willReturn(true);
        $terminal->expects($this->once())->method('enableBracketedPaste');
        $terminal->expects($this->once())->method('isBracketedPasteEnabled')->willReturn(true);
        $terminal->expects($this->once())->method('disableBracketedPaste');
        $terminal->expects($this->once())->method('disableRawMode');

        $session = new InteractiveSession($terminal);
        $session->setBracketedPaste(true);
        $session->start();
        $session->stop();
        $session->stop();
    }

    public function testStartThrowsIfRawModeCannotBeEnabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to enable raw mode');

        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())->method('enableRawMode')->willReturn(false);
        $terminal->expects($this->never())->method('enableBracketedPaste');

        $session = new InteractiveSession($terminal);
        $session->setBracketedPaste(true);
        $session->start();
    }
}
