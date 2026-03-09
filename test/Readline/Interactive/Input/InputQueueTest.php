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
use Psy\Readline\Interactive\Input\Key;
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

    public function testReplayPushesEventToFrontOfQueue(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readKey')
            ->willReturn(new Key('z', Key::TYPE_CHAR));

        $queue = new InputQueue($terminal);
        $queue->replay(new Key('a', Key::TYPE_CHAR));
        $queue->replay(new Key('b', Key::TYPE_CHAR));

        $this->assertSame('b', $queue->read()->getValue());
        $this->assertSame('a', $queue->read()->getValue());
        $this->assertSame('z', $queue->read()->getValue());
    }

    public function testQueueNormalizesCarriageReturnToLineFeed(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readKey')
            ->willReturn(new Key("\r", Key::TYPE_CHAR));

        $queue = new InputQueue($terminal);
        $event = $queue->read();

        $this->assertSame("\n", $event->getValue());
        $this->assertTrue($event->isChar());
    }

    public function testQueueNormalizesCsiUEventTypeSuffix(): void
    {
        $terminal = $this->mockTerminal();
        $terminal->expects($this->once())
            ->method('readKey')
            ->willReturn(new Key("\033[13;2:1u", Key::TYPE_ESCAPE));

        $queue = new InputQueue($terminal);
        $event = $queue->read();

        $this->assertSame("\033[13;2u", $event->getValue());
        $this->assertTrue($event->isEscape());
    }
}
