<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Util\Tty;

class TtyTest extends \Psy\Test\TestCase
{
    public function testMemoryStreamIsNotATty()
    {
        $stream = \fopen('php://memory', 'r');
        $this->assertFalse(Tty::isatty($stream));
        \fclose($stream);
    }

    public function testTempFileIsNotATty()
    {
        $stream = \tmpfile();
        $this->assertFalse(Tty::isatty($stream));
        \fclose($stream);
    }

    public function testPipeIsNotATty()
    {
        $pipes = [];
        $proc = \proc_open('echo hello', [1 => ['pipe', 'w']], $pipes);
        $this->assertFalse(Tty::isatty($pipes[1]));
        \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \proc_close($proc);
    }
}
