<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A BufferedOutput that supports the page() and paging methods used by ShellOutput.
 */
class TestOutput extends BufferedOutput
{
    public function page($messages, int $type = 0)
    {
        if (\is_string($messages)) {
            $messages = (array) $messages;
        }

        if (\is_callable($messages)) {
            $messages($this);
        } else {
            $this->write($messages, true, $type);
        }
    }

    public function startPaging()
    {
        // no-op for testing
    }

    public function stopPaging()
    {
        // no-op for testing
    }
}
