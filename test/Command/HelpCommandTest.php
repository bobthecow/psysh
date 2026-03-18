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

use Psy\Command\HelpCommand;
use Psy\Shell;
use Psy\Util\Tty;
use Symfony\Component\Console\Tester\CommandTester;

class HelpCommandTest extends \Psy\Test\TestCase
{
    protected function tearDown(): void
    {
        \putenv('COLUMNS');
    }

    public function testExecute()
    {
        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('Show a list of commands. Type `help [foo]` for', $tester->getDisplay());
        $this->assertStringContainsString('information about [foo].', $tester->getDisplay());

        foreach ($shell->all() as $command) {
            $pattern = \sprintf('/^\s*%s/m', \preg_quote($command->getName()));
            $this->assertMatchesRegularExpression($pattern, $tester->getDisplay());
        }

        $this->assertStringContainsString('End the current session and return to caller.', $tester->getDisplay());
        $this->assertStringContainsString('Aliases: quit, q', $tester->getDisplay());
    }

    public function testExecuteWrapsAliasesOnNarrowTerminals()
    {
        if (Tty::supportsStty() && \defined('STDOUT') && Tty::isatty(\STDOUT)) {
            $this->markTestSkipped('COLUMNS overrides are ignored when a live TTY width is available.');
        }

        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        \putenv('COLUMNS=50');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();

        $this->assertMatchesRegularExpression('/End the current session and return\s+to\s+caller\.\s+\n\s+Aliases: quit, q/', $display);
    }

    public function testExecuteKeepsAliasColumnOnWideTerminals()
    {
        if (Tty::supportsStty() && \defined('STDOUT') && Tty::isatty(\STDOUT)) {
            $this->markTestSkipped('COLUMNS overrides are ignored when a live TTY width is available.');
        }

        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        \putenv('COLUMNS=100');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();

        $this->assertMatchesRegularExpression('/End the current session and return to caller\.\s+Aliases: quit, q/', $display);
        $this->assertStringNotContainsString("End the current session and return to caller.\n", $display);
    }

    public function testExecuteDoesNotPushAliasesToFarRightOnVeryWideTerminals()
    {
        if (Tty::supportsStty() && \defined('STDOUT') && Tty::isatty(\STDOUT)) {
            $this->markTestSkipped('COLUMNS overrides are ignored when a live TTY width is available.');
        }

        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        \putenv('COLUMNS=200');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $line = null;

        foreach (\explode("\n", $display) as $candidate) {
            if (\strpos($candidate, 'End the current session and return to caller.') !== false) {
                $line = $candidate;
                break;
            }
        }

        $this->assertNotNull($line);
        $this->assertNotFalse($lineAliasPos = \strpos($line, 'Aliases: quit, q'));
        $this->assertNotFalse($lineCallerPos = \strpos($line, 'caller.'));
        $this->assertLessThan(60, $lineAliasPos - $lineCallerPos);
    }

    public function testExecuteCommandHelpWithCommandTester()
    {
        $shell = new Shell();
        $command = new HelpCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);
        $tester->execute(['command_name' => 'help']);

        $this->assertStringContainsString('Usage:', $tester->getDisplay());
        $this->assertStringContainsString('help [<command_name>]', $tester->getDisplay());
    }
}
