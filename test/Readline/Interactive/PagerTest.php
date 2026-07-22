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

use Psy\Readline\Interactive\Input\EofEvent;
use Psy\Readline\Interactive\Input\InputEvent;
use Psy\Readline\Interactive\Input\InputQueue;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Input\MouseEvent;
use Psy\Readline\Interactive\InteractiveSession;
use Psy\Readline\Interactive\Pager;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\LineMetrics;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class PagerTest extends TestCase
{
    private Pager $pager;

    protected function setUp(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(80);

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));

        $this->pager = new Pager(
            $terminal,
            $this->createMock(InteractiveSession::class),
            new InputQueue($terminal),
            $frameRenderer,
        );
    }

    public function testScrollDownAdvancesOffset(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('j', KeyEvent::TYPE_CHAR));

        $this->assertSame(1, $this->pager->getScrollOffset());
    }

    public function testSingleWrappedLineThatDoesNotFitUsesInteractivePager(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(40);
        $terminal->expects($this->once())->method('enableMouseReporting')->with(false);

        $session = $this->createMock(InteractiveSession::class);
        $session->expects($this->once())->method('start');
        $session->expects($this->once())->method('stop');

        $inputQueue = $this->createMock(InputQueue::class);
        $inputQueue->expects($this->once())
            ->method('read')
            ->willReturn(new EofEvent());

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $frameRenderer->expects($this->once())->method('renderFullScreenWidget');

        $pager = new Pager($terminal, $session, $inputQueue, $frameRenderer);
        $pager->page([\str_repeat('x', 400)]);
    }

    public function testDoesNotEmitScrollbackWhenRenderThrows(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(40);
        $terminal->expects($this->never())->method('write');

        $session = $this->createMock(InteractiveSession::class);
        $session->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start');
        $session->expects($this->once())->method('stop');

        $inputQueue = $this->createMock(InputQueue::class);
        $inputQueue->expects($this->never())->method('read');

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $frameRenderer->expects($this->once())
            ->method('renderFullScreenWidget')
            ->willThrowException(new \RuntimeException('render failed'));

        $pager = new Pager($terminal, $session, $inputQueue, $frameRenderer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('render failed');

        $pager->page([\str_repeat('x', 400)]);
    }

    public function testArrowDownAlsoScrolls(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent("\x1b[B", KeyEvent::TYPE_ESCAPE));

        $this->assertSame(1, $this->pager->getScrollOffset());
    }

    public function testScrollUpClampsAtZero(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('k', KeyEvent::TYPE_CHAR));

        $this->assertSame(0, $this->pager->getScrollOffset());
    }

    public function testPageDownAdvancesByViewport(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent(' ', KeyEvent::TYPE_CHAR));

        // Terminal height 10 gives a 9-row viewport, so page-down moves by
        // max(1, 9 - 1) rows.
        $this->assertSame(8, $this->pager->getScrollOffset());
    }

    public function testJumpToBottomGoesToMaxScroll(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('G', KeyEvent::TYPE_CHAR));

        // 50 - 9 = 41.
        $this->assertSame(41, $this->pager->getScrollOffset());
    }

    public function testJumpToTopFromMiddle(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('G', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('g', KeyEvent::TYPE_CHAR));

        $this->assertSame(0, $this->pager->getScrollOffset());
    }

    public function testQuitOnQIsGraceful(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('q', KeyEvent::TYPE_CHAR));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('graceful', $this->pager->getExitMode());
    }

    public function testCtrlCAborts(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent("\x03", KeyEvent::TYPE_CONTROL));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('aborted', $this->pager->getExitMode());
    }

    public function testEscapeAborts(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent("\x1b", KeyEvent::TYPE_ESCAPE));

        $this->assertSame('aborted', $this->pager->getExitMode());
    }

    public function testScrollDownPastBottomQuitsGracefully(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('G', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('j', KeyEvent::TYPE_CHAR));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('graceful', $this->pager->getExitMode());
    }

    public function testSlashEntersSearchInput(): void
    {
        $this->pager->resetState(['foo', 'bar']);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));

        $this->assertTrue($this->pager->isSearchInputActive());
    }

    public function testTypingExtendsSearchQuery(): void
    {
        $this->pager->resetState(['foo', 'bar', 'foobar']);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('f', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('o', KeyEvent::TYPE_CHAR));

        $this->assertSame('fo', $this->pager->getSearchQuery());
        $this->assertSame(2, $this->pager->getMatchCount());
    }

    public function testEnterCommitsSearch(): void
    {
        $this->pager->resetState(['foo', 'bar']);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('f', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent("\r", KeyEvent::TYPE_CHAR));

        $this->assertFalse($this->pager->isSearchInputActive());
    }

    public function testEscapeInSearchInputCancels(): void
    {
        $this->pager->resetState(['foo']);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('f', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent("\x1b", KeyEvent::TYPE_ESCAPE));

        $this->assertFalse($this->pager->isSearchInputActive());
        $this->assertSame('', $this->pager->getSearchQuery());
        // Cancelling search does NOT abort the pager.
        $this->assertFalse($this->pager->isQuitting());
    }

    public function testBackspaceTrimsSearchQuery(): void
    {
        $this->pager->resetState(['foo']);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('f', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('o', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent("\x7f", KeyEvent::TYPE_CONTROL));

        $this->assertSame('f', $this->pager->getSearchQuery());
    }

    public function testMouseWheelDownScrolls(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_WHEEL_DOWN, 1, 1));

        // Three lines per wheel tick.
        $this->assertSame(3, $this->pager->getScrollOffset());
    }

    public function testMouseWheelUpScrolls(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleEvent(new KeyEvent('G', KeyEvent::TYPE_CHAR));
        $offsetAtBottom = $this->pager->getScrollOffset();
        $this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_WHEEL_UP, 1, 1));

        $this->assertSame($offsetAtBottom - 3, $this->pager->getScrollOffset());
    }

    public function testGenericPagerDoesNotFollowManualLinks(): void
    {
        $link = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $this->pager->resetState([$link]);

        $this->assertFalse($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 1, 1)));
        $this->assertFalse($this->pager->isQuitting());
    }

    public function testRejectsUnknownInputEvents(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported input event');

        $this->pager->handleEvent(new class() extends InputEvent {
        });
    }

    public function testSearchScrollsToFirstMatch(): void
    {
        $lines = \array_fill(0, 30, 'noise');
        $lines[25] = 'needle';
        $this->pager->resetState($lines);
        $this->pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('n', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('e', KeyEvent::TYPE_CHAR));
        $this->pager->handleEvent(new KeyEvent('e', KeyEvent::TYPE_CHAR));

        $this->assertSame(1, $this->pager->getMatchCount());
        $this->assertGreaterThan(0, $this->pager->getScrollOffset());
        $this->assertLessThanOrEqual(25, $this->pager->getScrollOffset());
    }

    public function testSearchScrollsMatchIntoViewWithWrappedLines(): void
    {
        // Narrow terminal so 100-char lines wrap to 3 rows each. The viewport
        // is 9 rows, so only 3 lines fit at a time.
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(40);

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));

        $pager = new Pager(
            $terminal,
            $this->createMock(InteractiveSession::class),
            $this->createMock(InputQueue::class),
            $frameRenderer,
        );

        $lines = \array_fill(0, 100, \str_repeat('x', 100));
        $lines[50] = \str_repeat('x', 50).' needle '.\str_repeat('y', 30);
        $pager->resetState($lines);

        $pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        foreach (\str_split('needle') as $c) {
            $pager->handleEvent(new KeyEvent($c, KeyEvent::TYPE_CHAR));
        }
        $pager->handleEvent(new KeyEvent("\r", KeyEvent::TYPE_CHAR));

        // Match is at line 50; with 3-row lines and a 9-row viewport, three
        // lines fit. ScrollOffset 48 puts lines 48,49,50 in view.
        $this->assertSame(48, $pager->getScrollOffset());
    }
}
