<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Clipboard;

use Psy\Clipboard\Osc52ClipboardMethod;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class Osc52ClipboardMethodTest extends TestCase
{
    /** @var string|false */
    private $originalTmux;

    protected function setUp(): void
    {
        $this->originalTmux = \getenv('TMUX');
    }

    protected function tearDown(): void
    {
        if ($this->originalTmux === false) {
            \putenv('TMUX');
        } else {
            \putenv('TMUX='.$this->originalTmux);
        }
    }

    public function testWritesOsc52Sequence()
    {
        \putenv('TMUX');

        $output = new BufferedOutput();
        $method = new Osc52ClipboardMethod();

        $this->assertTrue($method->copy('hello', $output));
        $this->assertSame("\x1b]52;c;aGVsbG8=\x07", $output->fetch());
    }

    public function testWrapsSequenceForTmux()
    {
        \putenv('TMUX=1');

        $output = new BufferedOutput();
        $method = new Osc52ClipboardMethod();

        $this->assertTrue($method->copy('hello', $output));
        $this->assertSame("\x1bPtmux;\x1b\x1b\x1b]52;c;aGVsbG8=\x07\x1b\\", $output->fetch());
    }
}
