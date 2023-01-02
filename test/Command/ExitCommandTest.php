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

use Psy\Command\ExitCommand;
use Symfony\Component\Console\Tester\CommandTester;

class ExitCommandTest extends \Psy\Test\TestCase
{
    public function testExecute()
    {
        $this->expectException(\Psy\Exception\BreakException::class);
        $this->expectExceptionMessage('Goodbye');

        $command = new ExitCommand();
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->fail();
    }
}
