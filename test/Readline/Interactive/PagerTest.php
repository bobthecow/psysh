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

use Psy\Readline\Interactive\Input\InputQueue;
use Psy\Readline\Interactive\Input\Key;
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
            $this->createMock(InputQueue::class),
            $frameRenderer,
        );
    }

    public function testScrollDownAdvancesOffset(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('j', Key::TYPE_CHAR));

        $this->assertSame(1, $this->pager->getScrollOffset());
    }

    public function testSingleWrappedLineThatDoesNotFitUsesInteractivePager(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(40);

        $session = $this->createMock(InteractiveSession::class);
        $session->expects($this->once())->method('start');
        $session->expects($this->once())->method('stop');

        $inputQueue = $this->createMock(InputQueue::class);
        $inputQueue->expects($this->once())
            ->method('read')
            ->willReturn(new Key('', Key::TYPE_EOF));

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
        $this->pager->handleKey(new Key("\x1b[B", Key::TYPE_ESCAPE));

        $this->assertSame(1, $this->pager->getScrollOffset());
    }

    public function testScrollUpClampsAtZero(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('k', Key::TYPE_CHAR));

        $this->assertSame(0, $this->pager->getScrollOffset());
    }

    public function testPageDownAdvancesByViewport(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key(' ', Key::TYPE_CHAR));

        // Terminal height 10 gives a 9-row viewport, so page-down moves by
        // max(1, 9 - 1) rows.
        $this->assertSame(8, $this->pager->getScrollOffset());
    }

    public function testJumpToBottomGoesToMaxScroll(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('G', Key::TYPE_CHAR));

        // 50 - 9 = 41.
        $this->assertSame(41, $this->pager->getScrollOffset());
    }

    public function testJumpToTopFromMiddle(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('G', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('g', Key::TYPE_CHAR));

        $this->assertSame(0, $this->pager->getScrollOffset());
    }

    public function testQuitOnQIsGraceful(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('q', Key::TYPE_CHAR));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('graceful', $this->pager->getExitMode());
    }

    public function testCtrlCAborts(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key("\x03", Key::TYPE_CONTROL));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('aborted', $this->pager->getExitMode());
    }

    public function testEscapeAborts(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key("\x1b", Key::TYPE_ESCAPE));

        $this->assertSame('aborted', $this->pager->getExitMode());
    }

    public function testScrollDownPastBottomQuitsGracefully(): void
    {
        $this->pager->resetState(\array_fill(0, 50, 'line'));
        $this->pager->handleKey(new Key('G', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('j', Key::TYPE_CHAR));

        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('graceful', $this->pager->getExitMode());
    }

    public function testSlashEntersSearchInput(): void
    {
        $this->pager->resetState(['foo', 'bar']);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));

        $this->assertTrue($this->pager->isSearchInputActive());
    }

    public function testTypingExtendsSearchQuery(): void
    {
        $this->pager->resetState(['foo', 'bar', 'foobar']);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('f', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('o', Key::TYPE_CHAR));

        $this->assertSame('fo', $this->pager->getSearchQuery());
        $this->assertSame(2, $this->pager->getMatchCount());
    }

    public function testEnterCommitsSearch(): void
    {
        $this->pager->resetState(['foo', 'bar']);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('f', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key("\r", Key::TYPE_CHAR));

        $this->assertFalse($this->pager->isSearchInputActive());
    }

    public function testEscapeInSearchInputCancels(): void
    {
        $this->pager->resetState(['foo']);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('f', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key("\x1b", Key::TYPE_ESCAPE));

        $this->assertFalse($this->pager->isSearchInputActive());
        $this->assertSame('', $this->pager->getSearchQuery());
        // Cancelling search does NOT abort the pager.
        $this->assertFalse($this->pager->isQuitting());
    }

    public function testBackspaceTrimsSearchQuery(): void
    {
        $this->pager->resetState(['foo']);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('f', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('o', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key("\x7f", Key::TYPE_CONTROL));

        $this->assertSame('f', $this->pager->getSearchQuery());
    }

    public function testSearchScrollsToFirstMatch(): void
    {
        $lines = \array_fill(0, 30, 'noise');
        $lines[25] = 'needle';
        $this->pager->resetState($lines);
        $this->pager->handleKey(new Key('/', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('n', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('e', Key::TYPE_CHAR));
        $this->pager->handleKey(new Key('e', Key::TYPE_CHAR));

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

        $pager->handleKey(new Key('/', Key::TYPE_CHAR));
        foreach (\str_split('needle') as $c) {
            $pager->handleKey(new Key($c, Key::TYPE_CHAR));
        }
        $pager->handleKey(new Key("\r", Key::TYPE_CHAR));

        // Match is at line 50; with 3-row lines and a 9-row viewport, three
        // lines fit. ScrollOffset 48 puts lines 48,49,50 in view.
        $this->assertSame(48, $pager->getScrollOffset());
    }
}
