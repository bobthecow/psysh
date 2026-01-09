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

final class ClipboardFactory
{
    private const KNOWN_COMMANDS = [
        'wl-copy',
        'xsel --clipboard --input',
        'xclip -selection clipboard',
        'pbcopy',
        'clip.exe',
    ];

    private bool $allowOsc52;

    public function __construct(bool $allowOsc52 = false)
    {
        $this->allowOsc52 = $allowOsc52;
    }

    public function create(): ClipboardMethod
    {
        $isSsh = \getenv('SSH_TTY') !== false || \getenv('SSH_CLIENT') !== false;
        if ($isSsh) {
            return $this->allowOsc52
                ? new Osc52ClipboardMethod()
                : new NullClipboardMethod();
        }

        if (\function_exists('shell_exec') && \function_exists('proc_open')) {
            foreach (self::KNOWN_COMMANDS as $command) {
                if ($this->commandExists($command)) {
                    return new CommandClipboardMethod($command);
                }
            }
        }

        if ($this->allowOsc52) {
            return new Osc52ClipboardMethod();
        }

        return new NullClipboardMethod();
    }

    /**
     * @phpstan-param non-empty-string $command
     */
    private function commandExists(string $command): bool
    {
        $bin = \explode(' ', $command)[0];
        $result = \shell_exec(\sprintf("command -v %s 2>/dev/null", \escapeshellarg($bin)));

        return $result !== null && \trim($result) !== '';
    }
}
