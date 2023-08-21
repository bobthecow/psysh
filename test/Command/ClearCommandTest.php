<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\ClearCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class ClearCommandTest extends \Psy\Test\TestCase
{
    public function testExecute()
    {
        $command = new ClearCommand();
        $tester = new CommandTester($command);
        $tester->execute([]);

        $clear = \sprintf('%c[2J%c[0;0f', 27, 27);
        $this->assertStringContainsString($clear, $tester->getDisplay());
    }
}
