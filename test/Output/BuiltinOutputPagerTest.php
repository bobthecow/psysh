<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Output;

use Psy\Output\BuiltinOutputPager;
use Psy\Readline\Interactive\Pager;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\StreamOutput;

class BuiltinOutputPagerTest extends TestCase
{
    public function testBuffersFullLinesAndHandsToPagerOnClose(): void
    {
        $captured = null;
        $pager = $this->createMock(Pager::class);
        $pager->expects($this->once())
            ->method('page')
            ->willReturnCallback(function (array $lines) use (&$captured) {
                $captured = $lines;
            });

        $output = new BuiltinOutputPager($this->newStream(), $pager);
        $output->doWrite("one\n", false);
        $output->doWrite("two\n", false);
        $output->doWrite('three', true);
        $output->close();

        $this->assertSame(['one', 'two', 'three'], $captured);
    }

    public function testHandlesMultilineWriteSplitAcrossNewlines(): void
    {
        $captured = null;
        $pager = $this->createMock(Pager::class);
        $pager->method('page')->willReturnCallback(function (array $lines) use (&$captured) {
            $captured = $lines;
        });

        $output = new BuiltinOutputPager($this->newStream(), $pager);
        $output->doWrite("a\nb\nc", true);
        $output->close();

        $this->assertSame(['a', 'b', 'c'], $captured);
    }

    public function testPreservesPartialLineAcrossWrites(): void
    {
        $captured = null;
        $pager = $this->createMock(Pager::class);
        $pager->method('page')->willReturnCallback(function (array $lines) use (&$captured) {
            $captured = $lines;
        });

        $output = new BuiltinOutputPager($this->newStream(), $pager);
        $output->doWrite('hello ', false);
        $output->doWrite("world\n", false);
        $output->close();

        $this->assertSame(['hello world'], $captured);
    }

    public function testFlushesTrailingPartialLineOnClose(): void
    {
        $captured = null;
        $pager = $this->createMock(Pager::class);
        $pager->method('page')->willReturnCallback(function (array $lines) use (&$captured) {
            $captured = $lines;
        });

        $output = new BuiltinOutputPager($this->newStream(), $pager);
        $output->doWrite('no newline at end', false);
        $output->close();

        $this->assertSame(['no newline at end'], $captured);
    }

    public function testCloseWithNoOutputDoesNotInvokePager(): void
    {
        $pager = $this->createMock(Pager::class);
        $pager->expects($this->never())->method('page');

        $output = new BuiltinOutputPager($this->newStream(), $pager);
        $output->close();
    }

    public function testCloseResetsBufferForNextSession(): void
    {
        $calls = [];
        $pager = $this->createMock(Pager::class);
        $pager->method('page')->willReturnCallback(function (array $lines) use (&$calls) {
            $calls[] = $lines;
        });

        $output = new BuiltinOutputPager($this->newStream(), $pager);

        $output->doWrite("first\n", false);
        $output->close();

        $output->doWrite("second\n", false);
        $output->close();

        $this->assertSame([['first'], ['second']], $calls);
    }

    private function newStream(): StreamOutput
    {
        return new StreamOutput(\fopen('php://memory', 'w+'));
    }
}
