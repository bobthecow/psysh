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
use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\CommandOptionSource;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @group isolation-fail
 */
class CommandOptionSourceTest extends TestCase
{
    private function createMockCommand(string $name, array $options = [], array $aliases = []): Command
    {
        $command = new class($name, $options, $aliases) extends Command {
            private string $commandName;
            private array $commandOptions;
            private array $commandAliases;

            public function __construct(string $name, array $options, array $aliases)
            {
                $this->commandName = $name;
                $this->commandOptions = $options;
                $this->commandAliases = $aliases;
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->setName($this->commandName)
                    ->setAliases($this->commandAliases)
                    ->setDescription('Test command');

                foreach ($this->commandOptions as $option) {
                    $this->addOption(
                        $option['name'],
                        $option['shortcut'] ?? null,
                        $option['mode'] ?? InputOption::VALUE_NONE,
                        $option['description'] ?? ''
                    );
                }
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        return $command;
    }

    public function testAppliesToCommandOptionContext()
    {
        $source = new CommandOptionSource([]);
        $this->assertTrue($source->appliesToKind(CompletionKind::COMMAND_OPTION));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $source = new CommandOptionSource([]);
        $this->assertFalse($source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::COMMAND));
        $this->assertFalse($source->appliesToKind(CompletionKind::KEYWORD));
    }

    public function testLongOptionCompletion()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'long', 'shortcut' => 'l'],
                ['name' => 'help', 'shortcut' => 'h'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('--all', $completions);
        $this->assertContains('--long', $completions);
        $this->assertContains('--help', $completions);
    }

    public function testShortOptionCompletion()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'long', 'shortcut' => 'l'],
                ['name' => 'help', 'shortcut' => 'h'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '-', 'ls');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('-a', $completions);
        $this->assertContains('-l', $completions);
        $this->assertContains('-h', $completions);
    }

    public function testReturnsAllOptionsRegardlessOfPrefix()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'long', 'shortcut' => 'l'],
                ['name' => 'help', 'shortcut' => 'h'],
            ]),
        ];

        $source = new CommandOptionSource($commands);

        // Prefix filtering is handled by CompletionEngine's fuzzy matcher,
        // so the source returns all options regardless of prefix.
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--a', 'ls');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('--all', $completions);
        $this->assertContains('--long', $completions);
        $this->assertContains('-a', $completions);
        $this->assertContains('-l', $completions);
    }

    public function testCaseInsensitiveLongOptions()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'Long', 'shortcut' => 'l'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--L', 'ls');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('--Long', $completions);
    }

    public function testCommandAlias()
    {
        $commands = [
            $this->createMockCommand('list', [
                ['name' => 'all', 'shortcut' => 'a'],
            ], ['ls', 'll']),
        ];

        $source = new CommandOptionSource($commands);

        // Test with main command name
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'list');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);

        // Test with alias
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);

        // Test with another alias
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'll');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);
    }

    public function testUnknownCommand()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'unknown');
        $completions = $source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testReturnsAllOptionsForAnyPrefix()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'long', 'shortcut' => 'l'],
            ]),
        ];

        $source = new CommandOptionSource($commands);

        // Even with a non-matching prefix, all options are returned
        // because filtering is done by CompletionEngine's fuzzy matcher.
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--xyz', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);
        $this->assertContains('-a', $completions);

        // Empty prefix also returns both long and short options.
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);
        $this->assertContains('--long', $completions);
        $this->assertContains('-a', $completions);
        $this->assertContains('-l', $completions);
    }

    public function testSortingOrder()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'zzz', 'shortcut' => 'z'],
                ['name' => 'aaa', 'shortcut' => 'a'],
                ['name' => 'mmm', 'shortcut' => 'm'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);

        // All options (long and short) sorted alphabetically.
        $this->assertEquals(['--aaa', '--mmm', '--zzz', '-a', '-m', '-z'], $completions);
    }

    public function testMultipleCommands()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all'],
            ]),
            $this->createMockCommand('doc', [
                ['name' => 'help'],
            ]),
        ];

        $source = new CommandOptionSource($commands);

        // Test first command
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);
        $this->assertNotContains('--help', $completions);

        // Test second command
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'doc');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--help', $completions);
        $this->assertNotContains('--all', $completions);
    }

    public function testOptionsWithoutShortcuts()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'verbose'],  // No shortcut
                ['name' => 'quiet', 'shortcut' => 'q'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '', 'ls');
        $completions = $source->getCompletions($analysis);

        // Returns long options for all, plus short options where available.
        $this->assertContains('--verbose', $completions);
        $this->assertContains('--quiet', $completions);
        $this->assertContains('-q', $completions);
        $this->assertCount(3, $completions);
    }

    public function testUsedLongOptionIsNotSuggestedAgain()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'functions', 'shortcut' => 'f'],
                ['name' => 'long', 'shortcut' => 'l'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(
            CompletionKind::COMMAND_OPTION,
            '--',
            'ls',
            [],
            null,
            [],
            'ls --functions --'
        );
        $completions = $source->getCompletions($analysis);

        $this->assertContains('--all', $completions);
        $this->assertContains('--long', $completions);
        $this->assertNotContains('--functions', $completions);
    }

    public function testUsedShortOptionsAreNotSuggestedAgain()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'all', 'shortcut' => 'a'],
                ['name' => 'functions', 'shortcut' => 'f'],
                ['name' => 'long', 'shortcut' => 'l'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(
            CompletionKind::COMMAND_OPTION,
            '-',
            'ls',
            [],
            null,
            [],
            'ls -al -'
        );
        $completions = $source->getCompletions($analysis);

        $this->assertContains('-f', $completions);
        $this->assertNotContains('-a', $completions);
        $this->assertNotContains('-l', $completions);
    }

    public function testRepeatableOptionCanBeSuggestedAgain()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'tag', 'shortcut' => 't', 'mode' => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY],
                ['name' => 'verbose', 'shortcut' => 'v'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(
            CompletionKind::COMMAND_OPTION,
            '--',
            'ls',
            [],
            null,
            [],
            'ls --tag foo --'
        );
        $completions = $source->getCompletions($analysis);

        $this->assertContains('--tag', $completions);
        $this->assertContains('--verbose', $completions);
    }

    public function testRepeatableShortOptionCanBeSuggestedAgain()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'tag', 'shortcut' => 't', 'mode' => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY],
                ['name' => 'verbose', 'shortcut' => 'v'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(
            CompletionKind::COMMAND_OPTION,
            '-',
            'ls',
            [],
            null,
            [],
            'ls -t foo -'
        );
        $completions = $source->getCompletions($analysis);

        $this->assertContains('-t', $completions);
        $this->assertContains('-v', $completions);
    }

    public function testSetCommandsUpdatesCompletions()
    {
        $source = new CommandOptionSource([
            $this->createMockCommand('ls', [
                ['name' => 'all'],
            ]),
        ]);

        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--all', $completions);

        // Update commands
        $source->setCommands([
            $this->createMockCommand('doc', [
                ['name' => 'help'],
            ]),
        ]);

        // Old command should not work anymore
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'ls');
        $completions = $source->getCompletions($analysis);
        $this->assertEmpty($completions);

        // New command should work
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'doc');
        $completions = $source->getCompletions($analysis);
        $this->assertContains('--help', $completions);
    }

    public function testPartialLongOptionReturnsAll()
    {
        $commands = [
            $this->createMockCommand('ls', [
                ['name' => 'verbose'],
                ['name' => 'version'],
                ['name' => 'help'],
            ]),
        ];

        $source = new CommandOptionSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--ver', 'ls');
        $completions = $source->getCompletions($analysis);

        // All options returned; fuzzy matcher handles prefix filtering.
        $this->assertContains('--verbose', $completions);
        $this->assertContains('--version', $completions);
        $this->assertContains('--help', $completions);
    }
}
