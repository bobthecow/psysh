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

final class CommandClipboardMethod implements ClipboardMethod
{
    private string $command;

    public function __construct(string $command)
    {
        $this->command = $command;
    }

    public function copy(string $text, OutputInterface $output): bool
    {
        $process = \proc_open($this->command, [0 => ['pipe', 'r']], $pipes);
        if ($process === false) {
            throw new \RuntimeException('Unable to start clipboard command.');
        }

        \fwrite($pipes[0], $text);
        \fclose($pipes[0]);
        \proc_close($process);

        return true;
    }
}
