<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Source;

use Psy\Command\Command;
use Psy\Command\ConfigCommand;
use Psy\CommandArgumentCompletionAware;
use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\CommandArgumentSource;
use Psy\Configuration;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandArgumentSourceTest extends TestCase
{
    public function testAppliesToUnknownAndSymbolContexts()
    {
        $source = new CommandArgumentSource([]);

        $this->assertTrue($source->appliesToKind(CompletionKind::COMMAND_ARGUMENT));
        $this->assertFalse($source->appliesToKind(CompletionKind::UNKNOWN));
        $this->assertFalse($source->appliesToKind(CompletionKind::SYMBOL));
        $this->assertFalse($source->appliesToKind(CompletionKind::COMMAND));
        $this->assertFalse($source->appliesToKind(CompletionKind::COMMAND_OPTION));
    }

    public function testReturnsEmptyForCommandsWithoutArgumentCompletion()
    {
        $source = new CommandArgumentSource([$this->createPlainCommand('help')]);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'help', [], null, [], 'help ');

        $this->assertSame([], $source->getCompletions($analysis));
    }

    public function testDelegatesToArgumentAwareCommand()
    {
        $source = new CommandArgumentSource([
            $this->createArgumentAwareCommand('config', ['cfg'], ['list', 'get', 'set']),
        ]);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config ');

        $this->assertSame(['list', 'get', 'set'], $source->getCompletions($analysis));
    }

    public function testResolvesCommandAliases()
    {
        $source = new CommandArgumentSource([
            $this->createArgumentAwareCommand('config', ['cfg'], ['list', 'get', 'set']),
        ]);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'cfg', [], null, [], 'cfg ');

        $this->assertSame(['list', 'get', 'set'], $source->getCompletions($analysis));
    }

    public function testSetCommandsUpdatesCompletions()
    {
        $source = new CommandArgumentSource([
            $this->createArgumentAwareCommand('config', [], ['list', 'get', 'set']),
        ]);

        $analysis = new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config ');
        $this->assertSame(['list', 'get', 'set'], $source->getCompletions($analysis));

        $source->setCommands([
            $this->createArgumentAwareCommand('prefs', [], ['show']),
        ]);

        $this->assertSame([], $source->getCompletions($analysis));
        $this->assertSame(
            ['show'],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'prefs', [], null, [], 'prefs '))
        );
    }

    public function testConfigCommandCompletesActionsKeysAndValues()
    {
        $command = new ConfigCommand();
        $command->setConfiguration($this->createConfiguration());

        $source = new CommandArgumentSource([$command]);

        $this->assertSame(
            ['list', 'get', 'set'],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config '))
        );
        $this->assertSame(
            ['list', 'get', 'set'],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, 'g', 'config', [], null, [], 'config g'))
        );

        $keyCompletions = $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config get '));
        $this->assertContains('verbosity', $keyCompletions);
        $this->assertContains('theme', $keyCompletions);

        $valueCompletions = $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config set verbosity '));
        $this->assertSame(['quiet', 'normal', 'verbose', 'very_verbose', 'debug'], $valueCompletions);
        $partialValueCompletions = $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, 'd', 'config', [], null, [], 'config set verbosity d'));
        $this->assertSame(['quiet', 'normal', 'verbose', 'very_verbose', 'debug'], $partialValueCompletions);

        $this->assertSame(
            ['auto'],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config set clipboardCommand '))
        );
        $this->assertSame(
            [],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config set errorLoggingLevel '))
        );
        $this->assertSame(
            [],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, 'pb', 'config', [], null, [], 'config set clipboardCommand pb'))
        );
        $this->assertSame(
            [],
            $source->getCompletions(new AnalysisResult(CompletionKind::COMMAND_ARGUMENT, '', 'config', [], null, [], 'config set verbosity debug '))
        );
    }

    private function createConfiguration(): Configuration
    {
        return new Configuration([
            'configFile'   => \dirname(__DIR__, 2).'/Fixtures/empty.php',
            'trustProject' => false,
        ]);
    }

    private function createPlainCommand(string $name): Command
    {
        return new class($name) extends Command {
            private string $commandName;

            public function __construct(string $name)
            {
                $this->commandName = $name;
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->setName($this->commandName)->setDescription('Test command');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
    }

    /**
     * @param string[] $aliases
     * @param string[] $completions
     */
    private function createArgumentAwareCommand(string $name, array $aliases, array $completions): Command
    {
        return new class($name, $aliases, $completions) extends Command implements CommandArgumentCompletionAware {
            private string $commandName;
            private array $commandAliases;
            private array $commandCompletions;

            public function __construct(string $name, array $aliases, array $completions)
            {
                $this->commandName = $name;
                $this->commandAliases = $aliases;
                $this->commandCompletions = $completions;
                parent::__construct();
            }

            protected function configure(): void
            {
                $this
                    ->setName($this->commandName)
                    ->setAliases($this->commandAliases)
                    ->setDescription('Test command');
            }

            public function getArgumentCompletions(AnalysisResult $analysis): array
            {
                return $this->commandCompletions;
            }

            public function supportsArgumentCompletion(AnalysisResult $analysis): bool
            {
                return true;
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
    }
}
