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

final class CommandClipboardMethod extends ClipboardMethod
{
    private string $command;

    public function __construct(string $command, ?int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false, ?\Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter = null)
    {
        parent::__construct($verbosity, $decorated, $formatter);
        $this->command = $command;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if (!\function_exists('proc_open')) {
            throw new \RuntimeException('proc_open is not available.');
        }

        $process = \proc_open($this->command, [0 => ['pipe', 'r']], $pipes);
        if ($process === false) {
            throw new \RuntimeException('Unable to start clipboard command.');
        }

        if ($newline) {
            $message .= "\n";
        }

        \fwrite($pipes[0], $message);
        \fclose($pipes[0]);
        \proc_close($process);
    }
}
