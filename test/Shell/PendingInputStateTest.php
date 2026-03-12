<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Shell;

use Psy\Shell\PendingInputState;
use Psy\Test\TestCase;

class PendingInputStateTest extends TestCase
{
    public function testAppendedLinesTrackCompleteness(): void
    {
        $state = new PendingInputState();

        $state->appendLine('if (true) {\\', false);
        $this->assertTrue($state->hasCode());
        $this->assertFalse($state->hasValidCode());

        $state->setPendingCode("if (true) {\n}");
        $state->appendLine('}', false);
        $this->assertTrue($state->hasValidCode());
    }

    public function testPushAndRestorePreviousCode(): void
    {
        $state = new PendingInputState();
        $state->appendLine('$a = 1;', false);
        $state->setPendingCode('$a = 1;');

        $state->pushCurrentCode();
        $state->clear();
        $state->appendLine('$b = 2;', false);
        $state->setPendingCode('$b = 2;');

        $state->restorePreviousCode();

        $this->assertSame(['$a = 1;'], $state->getPendingCodeBuffer());
        $this->assertSame('$a = 1;', $state->getPendingCode());
        $this->assertTrue($state->hasValidCode());
    }
}
