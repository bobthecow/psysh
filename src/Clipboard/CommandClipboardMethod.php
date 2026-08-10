<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Clipboard;

use Symfony\Component\Console\Output\OutputInterface;

class CommandClipboardMethod implements ClipboardMethod
{
    private string $command;

    public function __construct(string $command)
    {
        $this->command = $command;
    }

    public function copy(string $text, OutputInterface $output): bool
    {
        $nullDevice = \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = \proc_open($this->command, [
            0 => ['pipe', 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ], $pipes);
        if ($process === false) {
            return false;
        }

        $success = $this->writeAll($pipes[0], $text);
        \fclose($pipes[0]);
        $exitCode = \proc_close($process);

        return $success && $exitCode === 0;
    }

    /**
     * Write the full string to the pipe, returning false on failure.
     *
     * @param resource $pipe
     */
    private function writeAll($pipe, string $text): bool
    {
        $remaining = $text;

        while ($remaining !== '') {
            $written = \fwrite($pipe, $remaining);
            if ($written === false || $written === 0) {
                return false;
            }

            $remaining = (string) \substr($remaining, $written);
        }

        return true;
    }
}
