<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\TraceCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class TraceCommandTest extends \Psy\Test\TestCase
{
    public function testExecuteWithCommandTester()
    {
        $command = new TraceCommand();
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^\s*0:\s+/m', $tester->getDisplay());
    }
}
