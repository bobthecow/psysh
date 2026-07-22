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
use Psy\Readline\Interactive\Input\InputQueue;
use Psy\Readline\Interactive\Input\KeyEvent;
use Psy\Readline\Interactive\Input\MouseEvent;
use Psy\Readline\Interactive\Input\PasteEvent;
use Psy\Readline\Interactive\InteractiveSession;
use Psy\Readline\Interactive\ManualPager;
use Psy\Readline\Interactive\Renderer\Area;
use Psy\Readline\Interactive\Renderer\Frame;
use Psy\Readline\Interactive\Renderer\FrameRenderer;
use Psy\Readline\Interactive\Renderer\LineMetrics;
use Psy\Readline\Interactive\Terminal;
use Psy\Test\TestCase;

class ManualPagerTest extends TestCase
{
    private ManualPager $pager;
    private InputQueue $inputQueue;

    protected function setUp(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(80);

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));

        $this->inputQueue = new InputQueue($terminal);
        $this->pager = new ManualPager(
            $terminal,
            $this->createMock(InteractiveSession::class),
            $this->inputQueue,
            $frameRenderer,
        );
    }

    public function testClickingPhpManualLinkQueuesDocCommandAndQuitsGracefully(): void
    {
        $link = "\033]8;;https://php.net/array-values\033\\array_values()\033]8;;\033\\";
        $this->pager->resetState(['See '.$link]);

        $this->assertTrue($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 5, 1)));
        $this->assertTrue($this->pager->isQuitting());
        $this->assertSame('graceful', $this->pager->getExitMode());

        $command = $this->inputQueue->read();
        $submit = $this->inputQueue->read();
        $this->assertInstanceOf(PasteEvent::class, $command);
        $this->assertSame('doc array_values', $command->getContent());
        $this->assertInstanceOf(KeyEvent::class, $submit);
        $this->assertTrue($submit->isChar());
        $this->assertSame("\n", $submit->getValue());
    }

    public function testClickingWrappedPhpManualLinkQueuesDocCommand(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(10);

        $inputQueue = new InputQueue($terminal);
        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $pager = new ManualPager($terminal, $this->createMock(InteractiveSession::class), $inputQueue, $frameRenderer);

        $link = "\033]8;;https://php.net/random-engine-mt19937.generate\033\\Random\\Engine\\Mt19937::generate()\033]8;;\033\\";
        $pager->resetState([\str_repeat('x', 12).$link]);
        $pager->handleEvent(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 3, 2));

        $this->assertTrue($pager->isQuitting());
        $command = $inputQueue->read();
        $this->assertInstanceOf(PasteEvent::class, $command);
        $this->assertSame('doc Random\\Engine\\Mt19937::generate', $command->getContent());
    }

    public function testClickingPhpManualLinkAfterScrollingUsesVisibleLine(): void
    {
        $lines = \array_fill(0, 20, 'not a link');
        $lines[5] = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $this->pager->resetState($lines);
        for ($i = 0; $i < 5; $i++) {
            $this->pager->handleEvent(new KeyEvent('j', KeyEvent::TYPE_CHAR));
        }

        $this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 1, 1));

        $this->assertTrue($this->pager->isQuitting());
        $command = $this->inputQueue->read();
        $this->assertInstanceOf(PasteEvent::class, $command);
        $this->assertSame('doc time', $command->getContent());
    }

    public function testClickingNonPhpManualLinkDoesNothing(): void
    {
        $link = "\033]8;;https://example.com\033\\array_values()\033]8;;\033\\";
        $this->pager->resetState([$link]);

        $this->assertFalse($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 1, 1)));
        $this->assertFalse($this->pager->isQuitting());
    }

    public function testMouseMoveOnlyRequestsRenderWhenHoveredLinkChanges(): void
    {
        $link = "\033]8;;https://php.net/array-values\033\\array_values()\033]8;;\033\\";
        $this->pager->resetState(['See '.$link]);

        $this->assertTrue($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_MOVE, 5, 1)));
        $this->assertFalse($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_MOVE, 6, 1)));
        $this->assertTrue($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_MOVE, 1, 1)));
        $this->assertFalse($this->pager->handleEvent(new MouseEvent(MouseEvent::ACTION_MOVE, 2, 1)));
    }

    public function testHoveredPhpManualLinkIsUnderlined(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(80);
        $terminal->expects($this->once())->method('enableMouseReporting')->with(true);
        $terminal->method('format')->willReturnArgument(0);
        $terminal->method('useUnicode')->willReturn(false);

        $line = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $inputQueue = new InputQueue($terminal);
        $inputQueue->replay(new EofEvent());
        $inputQueue->replay(new MouseEvent(MouseEvent::ACTION_MOVE, 1, 1));

        $renderedLines = [];
        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $frameRenderer->expects($this->exactly(2))
            ->method('renderFullScreenWidget')
            ->willReturnCallback(function ($widget) use (&$renderedLines) {
                $frame = Frame::empty();
                $widget->render($frame, new Area(80, 10));
                $renderedLines[] = $frame->getLines()[0];
            });

        $pager = new ManualPager($terminal, $this->createMock(InteractiveSession::class), $inputQueue, $frameRenderer);
        $pager->page([$line]);

        $this->assertStringNotContainsString("\033[4mtime()\033[24m", $renderedLines[0]);
        $this->assertStringContainsString("\033[4mtime()\033[24m", $renderedLines[1]);
    }

    public function testSearchNavigationClearsHoveredLink(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(5);
        $terminal->method('getWidth')->willReturn(80);

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $pager = new class($terminal, $this->createMock(InteractiveSession::class), new InputQueue($terminal), $frameRenderer) extends ManualPager {
            /**
             * @param string[] $lines
             *
             * @return string[]
             */
            public function prepareLines(array $lines): array
            {
                return $this->prepareLinesForRender($lines);
            }
        };

        $link = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $lines = \array_merge([$link], \array_fill(0, 9, 'noise'), ['needle']);
        $pager->resetState($lines);
        $pager->handleEvent(new MouseEvent(MouseEvent::ACTION_MOVE, 1, 1));
        $this->assertStringContainsString("\033[4mtime()\033[24m", $pager->prepareLines($lines)[0]);

        $pager->handleEvent(new KeyEvent('/', KeyEvent::TYPE_CHAR));
        $pager->handleEvent(new KeyEvent('n', KeyEvent::TYPE_CHAR));

        $this->assertSame($link, $pager->prepareLines($lines)[0]);
    }

    public function testClickingShortPhpManualLinkUsesPagerAndEmitsScrollback(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $terminal->method('getHeight')->willReturn(10);
        $terminal->method('getWidth')->willReturn(80);

        $line = "\033]8;;https://php.net/time\033\\time()\033]8;;\033\\";
        $terminal->expects($this->once())->method('write')->with($line."\n");

        $session = $this->createMock(InteractiveSession::class);
        $session->expects($this->once())->method('start');
        $session->expects($this->once())->method('stop');

        $inputQueue = new InputQueue($terminal);
        $inputQueue->replay(new MouseEvent(MouseEvent::ACTION_RELEASE_LEFT, 1, 1));

        $frameRenderer = $this->createMock(FrameRenderer::class);
        $frameRenderer->method('getLineMetrics')->willReturn(new LineMetrics($terminal));
        $frameRenderer->expects($this->once())->method('renderFullScreenWidget');

        $pager = new ManualPager($terminal, $session, $inputQueue, $frameRenderer);
        $pager->page([$line]);

        $command = $inputQueue->read();
        $submit = $inputQueue->read();
        $this->assertInstanceOf(PasteEvent::class, $command);
        $this->assertSame('doc time', $command->getContent());
        $this->assertInstanceOf(KeyEvent::class, $submit);
        $this->assertSame("\n", $submit->getValue());
    }
}
