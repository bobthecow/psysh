<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Refiner;

use Psy\Command\Command;
use Psy\CommandArgumentCompletionAware;
use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Refiner\CommandContextRefiner;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandContextRefinerTest extends TestCase
{
    public function testRefinesTrailingCommandTailToCommandArgumentContext(): void
    {
        $refiner = new CommandContextRefiner([$this->createArgumentAwareCommand('config')]);
        $analysis = new AnalysisResult(CompletionKind::UNKNOWN, '', null, [], null, [], 'config ');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::COMMAND_ARGUMENT, $result->kinds);
        $this->assertSame('config', $result->leftSide);
    }

    public function testRefinesSymbolTailForArgumentAwareCommands(): void
    {
        $refiner = new CommandContextRefiner([$this->createArgumentAwareCommand('config', ['cfg'])]);
        $analysis = new AnalysisResult(CompletionKind::SYMBOL, 'g', null, [], null, [], 'cfg g');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::COMMAND_ARGUMENT, $result->kinds);
        $this->assertSame('cfg', $result->leftSide);
        $this->assertSame('g', $result->prefix);
    }

    public function testDoesNotRefineCommandOptions(): void
    {
        $refiner = new CommandContextRefiner([$this->createArgumentAwareCommand('config')]);
        $analysis = new AnalysisResult(CompletionKind::COMMAND_OPTION, '--', 'config', [], null, [], 'config --');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::COMMAND_OPTION, $result->kinds);
    }

    public function testDoesNotRefineCommandsWithoutArgumentCompletion(): void
    {
        $refiner = new CommandContextRefiner([$this->createPlainCommand('help')]);
        $analysis = new AnalysisResult(CompletionKind::SYMBOL, 'Foo', null, [], null, [], 'help Foo');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::SYMBOL, $result->kinds);
        $this->assertNull($result->leftSide);
    }

    public function testDoesNotRefineWhenCommandRequestsGenericFallback(): void
    {
        $refiner = new CommandContextRefiner([$this->createArgumentAwareCommand('config', [], false)]);
        $analysis = new AnalysisResult(CompletionKind::SYMBOL, 'E_', null, [], null, [], 'config set errorLoggingLevel E_');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::SYMBOL, $result->kinds);
        $this->assertNull($result->leftSide);
        $this->assertSame('E_', $result->prefix);
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
     */
    private function createArgumentAwareCommand(string $name, array $aliases = [], bool $supportsCompletion = true): Command
    {
        return new class($name, $aliases, $supportsCompletion) extends Command implements CommandArgumentCompletionAware {
            private string $commandName;
            private array $commandAliases;
            private bool $supportsCompletion;

            public function __construct(string $name, array $aliases, bool $supportsCompletion)
            {
                $this->commandName = $name;
                $this->commandAliases = $aliases;
                $this->supportsCompletion = $supportsCompletion;
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
                return [];
            }

            public function supportsArgumentCompletion(AnalysisResult $analysis): bool
            {
                return $this->supportsCompletion;
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };
    }
}
