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

final class Osc52ClipboardMethod extends ClipboardMethod
{
    public function __construct(?int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = false, ?\Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter = null)
    {
        parent::__construct($verbosity, $decorated, $formatter);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if ($newline) {
            $message .= "\n";
        }

        $base64 = \base64_encode($message);
        $osc52 = "\x1b]52;c;{$base64}\x07";

        if (\getenv('TMUX')) {
            $osc52 = "\x1bPtmux;\x1b" . \str_replace("\x1b", "\x1b\x1b", $osc52) . "\x1b\\";
        }

        \fwrite(\STDOUT, $osc52);
    }
}
