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

use Psy\Clipboard\CommandClipboardMethod;
use Psy\Test\TempPaths;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandClipboardMethodTest extends TestCase
{
    public function testCopyReturnsTrueWhenCommandSucceeds()
    {
        $target = TempPaths::file('psysh-test-copy-');
        $command = $this->phpCommand('file_put_contents('.\var_export($target, true).', stream_get_contents(STDIN));');

        $method = new CommandClipboardMethod($command);

        $this->assertTrue($method->copy('copied text', new BufferedOutput()));
        $this->assertSame('copied text', \file_get_contents($target));
    }

    public function testCopyReturnsFalseWhenCommandFails()
    {
        $command = $this->phpCommand('fwrite(STDERR, "fail"); exit(1);');
        $method = new CommandClipboardMethod($command);

        $this->assertFalse($method->copy('copied text', new BufferedOutput()));
    }

    public function testCopyDoesNotDeadlockWhenCommandWritesLargeDiscardedOutput()
    {
        $target = TempPaths::file('psysh-test-copy-');
        $command = $this->phpCommand(
            'fwrite(STDOUT, str_repeat("o", 1024 * 1024));'.
            'fwrite(STDERR, str_repeat("e", 1024 * 1024));'.
            'file_put_contents('.\var_export($target, true).', stream_get_contents(STDIN));'
        );
        $text = \str_repeat('copied text ', 100000);

        $method = new CommandClipboardMethod($command);

        $this->assertTrue($method->copy($text, new BufferedOutput()));
        $this->assertSame(\hash('sha256', $text), \hash_file('sha256', $target));
    }

    private function phpCommand(string $code): string
    {
        return \escapeshellarg(\PHP_BINARY).' -r '.\escapeshellarg($code);
    }
}
