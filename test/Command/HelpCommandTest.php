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

use Psy\Command\HelpCommand;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class HelpCommandTest extends \Psy\Test\TestCase
{
    public function testExecute()
    {
        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('Show a list of commands. Type `help [foo]` for information about [foo].', $tester->getDisplay());

        foreach ($shell->all() as $command) {
            $pattern = \sprintf('/^\s*%s/m', \preg_quote($command->getName()));
            $this->assertMatchesRegularExpression($pattern, $tester->getDisplay());
        }

        $this->assertStringContainsString('End the current session and return to caller.', $tester->getDisplay());
        $this->assertStringContainsString('Aliases: quit, q', $tester->getDisplay());
    }
}
