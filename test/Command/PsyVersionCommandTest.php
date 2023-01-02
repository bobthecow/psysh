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

use Psy\Command\PsyVersionCommand;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class PsyVersionCommandTest extends \Psy\Test\TestCase
{
    public function testExecute()
    {
        $command = new PsyVersionCommand();
        $command->setApplication(new Shell());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString(Shell::VERSION, $tester->getDisplay());
    }
}
