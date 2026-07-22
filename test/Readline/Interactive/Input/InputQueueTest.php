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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\Readline\Interactive\Input\InputQueue;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Input\MouseEvent;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class InputQueueTest extends TestCase
{
    /**
     * @return Terminal&MockObject
     */
    private function mockTerminal(): Terminal
    {
        return $this->createMock(Terminal::class);
    }

    public function testReplayPushesEventsToFrontInArgumentOrder(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readEvent')
            ->willReturn(new KeyEvent('z', KeyEvent::TYPE_CHAR));

        $queue = new InputQueue($terminal);
        $queue->replay(
            new KeyEvent('a', KeyEvent::TYPE_CHAR),
            new KeyEvent('b', KeyEvent::TYPE_CHAR),
        );
        $queue->replay(new KeyEvent('c', KeyEvent::TYPE_CHAR));

        foreach (['c', 'a', 'b', 'z'] as $expected) {
            $event = $queue->read();
            $this->assertInstanceOf(KeyEvent::class, $event);
            $this->assertSame($expected, $event->getValue());
        }
    }

    public function testQueueNormalizesCarriageReturnToLineFeed(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readEvent')
            ->willReturn(new KeyEvent("\r", KeyEvent::TYPE_CHAR));

        $queue = new InputQueue($terminal);
        $event = $queue->read();

        $this->assertInstanceOf(KeyEvent::class, $event);
        $this->assertSame("\n", $event->getValue());
        $this->assertTrue($event->isChar());
    }

    public function testQueueNormalizesCsiUEventTypeSuffix(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readEvent')
            ->willReturn(new KeyEvent("\033[13;2:1u", KeyEvent::TYPE_ESCAPE));

        $queue = new InputQueue($terminal);
        $event = $queue->read();

        $this->assertInstanceOf(KeyEvent::class, $event);
        $this->assertSame("\033[13;2u", $event->getValue());
        $this->assertTrue($event->isEscape());
    }

    public function testQueueReplaysNonKeyEventsWithoutNormalization(): void
    {
        $event = new MouseEvent(MouseEvent::ACTION_MOVE, 30, 15);
        $queue = new InputQueue($this->mockTerminal());
        $queue->replay($event);

        $this->assertSame($event, $queue->read());
    }
}
