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

use Psy\Clipboard\NullClipboardMethod;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class NullClipboardMethodTest extends TestCase
{
    public function testSshWarningIsPrintedOncePerInstance()
    {
        $output = new BufferedOutput();
        $method = new NullClipboardMethod(true);

        $this->assertFalse($method->copy('ignored', $output));

        $display = $output->fetch();
        $this->assertStringContainsString('Clipboard copy is unavailable over SSH.', $display);
        $this->assertStringContainsString('useOsc52Clipboard: true', $display);

        $this->assertFalse($method->copy('ignored', $output));
        $this->assertSame('', $output->fetch());
    }

    public function testConfiguredCommandHintIsShownForLocalSessions()
    {
        $output = new BufferedOutput();
        $method = new NullClipboardMethod(false, NullClipboardMethod::REASON_NO_COMMAND_SUPPORT);

        $this->assertFalse($method->copy('ignored', $output));

        $display = $output->fetch();
        $this->assertStringContainsString('Clipboard commands are unavailable in this PHP environment.', $display);
        $this->assertStringContainsString('clipboardCommand', $display);
    }

    public function testNoCommandHintIsShownForLocalSessions()
    {
        $output = new BufferedOutput();
        $method = new NullClipboardMethod(false, NullClipboardMethod::REASON_NO_COMMAND);

        $this->assertFalse($method->copy('ignored', $output));

        $display = $output->fetch();
        $this->assertStringContainsString('No clipboard command was found.', $display);
        $this->assertStringContainsString('clipboardCommand', $display);
    }
}
