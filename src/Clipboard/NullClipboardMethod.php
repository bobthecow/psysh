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

final class NullClipboardMethod implements ClipboardMethod
{
    private static bool $warned = false;
    private bool $isSsh;

    public function __construct(bool $isSsh)
    {
        $this->isSsh = $isSsh;
    }

    public function copy(string $text, OutputInterface $output): bool
    {
        if (self::$warned) {
            return false;
        }
        self::$warned = true;

        $output->writeln('<info>💡 Productivity Tip: Remote Clipboard Support</info>');
        $output->writeln('You can enable seamless clipboard copying by setting <comment>useOsc52Clipboard: true</comment> in your config.');

        if ($this->isSsh) {
            $output->writeln('<info>SSH detected:</info> OSC 52 is the only way to copy text from this remote server directly to your local machine.');
        }

        $output->writeln("\n<error>⚠️  Security Warning:</error>");
        $output->writeln('OSC 52 allows the terminal to <options=bold>write</> to your local clipboard.');
        $output->writeln('A malicious script or compromised server could "hijack" your clipboard by');
        $output->writeln('injecting dangerous commands (e.g., <comment>sudo rm -rf /</comment>) without your consent.');
        $output->writeln('<options=bold>Only enable this if you trust this server and the scripts you run on it.</>');
        $output->writeln('');

        return false;
    }
}
