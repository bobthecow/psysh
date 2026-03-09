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
use Psy\Completion\Source\CommandSource;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandSourceTest extends TestCase
{
    private function createMockCommand(string $name, array $aliases = []): Command
    {
        $command = new class($name, $aliases) extends Command {
            private string $commandName;
            private array $commandAliases;

            public function __construct(string $name, array $aliases)
            {
                $this->commandName = $name;
                $this->commandAliases = $aliases;
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->setName($this->commandName)
                    ->setAliases($this->commandAliases)
                    ->setDescription('Test command');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        return $command;
    }

    public function testAppliesToCommandContext()
    {
        $source = new CommandSource([]);
        $this->assertTrue($source->appliesToKind(CompletionKind::COMMAND));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $source = new CommandSource([]);
        $this->assertFalse($source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::STATIC_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::CLASS_NAME));
        $this->assertFalse($source->appliesToKind(CompletionKind::FUNCTION_NAME));
    }

    public function testEmptyPrefix()
    {
        $commands = [
            $this->createMockCommand('ls'),
            $this->createMockCommand('doc'),
            $this->createMockCommand('help'),
        ];

        $source = new CommandSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('ls', $completions);
        $this->assertContains('doc', $completions);
        $this->assertContains('help', $completions);

        // Should be sorted alphabetically
        $sorted = $completions;
        \sort($sorted);
        $this->assertEquals($sorted, $completions);
    }

    public function testReturnsAllCommandsRegardlessOfPrefix()
    {
        $commands = [
            $this->createMockCommand('ls'),
            $this->createMockCommand('list'),
            $this->createMockCommand('doc'),
            $this->createMockCommand('dump'),
        ];

        $source = new CommandSource($commands);

        // All prefixes should return the same set of all commands
        $testPrefixes = ['', 'l', 'L', 'xyz'];

        foreach ($testPrefixes as $prefix) {
            $analysis = new AnalysisResult(CompletionKind::COMMAND, $prefix);
            $completions = $source->getCompletions($analysis);

            $this->assertEquals(['doc', 'dump', 'list', 'ls'], $completions, "Failed for prefix: {$prefix}");
        }
    }

    public function testCommandAliases()
    {
        $commands = [
            $this->createMockCommand('list', ['ls', 'll']),
        ];

        $source = new CommandSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);

        $this->assertContains('list', $completions);
        $this->assertContains('ls', $completions);
        $this->assertContains('ll', $completions);
    }

    public function testEmptyCommandList()
    {
        $source = new CommandSource([]);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);

        $this->assertEmpty($completions);
    }

    public function testSortingOrder()
    {
        $commands = [
            $this->createMockCommand('zzz'),
            $this->createMockCommand('aaa'),
            $this->createMockCommand('mmm'),
            $this->createMockCommand('bbb'),
        ];

        $source = new CommandSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);

        $this->assertEquals(['aaa', 'bbb', 'mmm', 'zzz'], $completions);
    }

    public function testMultipleAliasesPerCommand()
    {
        $commands = [
            $this->createMockCommand('list', ['ls', 'll', 'dir']),
        ];

        $source = new CommandSource($commands);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);

        $this->assertCount(4, $completions);
        $this->assertContains('list', $completions);
        $this->assertContains('ls', $completions);
        $this->assertContains('ll', $completions);
        $this->assertContains('dir', $completions);
    }

    public function testSetCommandsUpdatesCompletions()
    {
        $source = new CommandSource([
            $this->createMockCommand('ls'),
        ]);

        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');
        $completions = $source->getCompletions($analysis);
        $this->assertEquals(['ls'], $completions);

        // Update commands
        $source->setCommands([
            $this->createMockCommand('doc'),
            $this->createMockCommand('help'),
        ]);

        $completions = $source->getCompletions($analysis);
        $this->assertContains('doc', $completions);
        $this->assertContains('help', $completions);
        $this->assertNotContains('ls', $completions);
    }
}
