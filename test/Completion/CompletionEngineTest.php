<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Command\Command;
use Psy\CommandArgumentCompletionAware;
use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionEngine;
use Psy\Completion\CompletionKind;
use Psy\Completion\CompletionRequest;
use Psy\Completion\Refiner\CommandContextRefiner;
use Psy\Completion\Source\SourceInterface;
use Psy\Completion\Source\VariableSource;
use Psy\Completion\SymbolCatalog;
use Psy\Context;
use Psy\Test\Fixtures\Completion\FixedResultSource;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompletionEngineTest extends TestCase
{
    public function testMultipleSourceInstancesAreNotClobbered(): void
    {
        $engine = new CompletionEngine(new Context());
        $engine->addSource(new FixedResultSource(['first']));
        $engine->addSource(new FixedResultSource(['second']));

        $result = $engine->getCompletions(new CompletionRequest('$', 1));

        $this->assertContains('first', $result);
        $this->assertContains('second', $result);
    }

    public function testSameSourceInstanceIsNotRegisteredTwice(): void
    {
        $engine = new CompletionEngine(new Context());
        $source = new FixedResultSource(['only_once']);
        $engine->addSource($source);
        $engine->addSource($source);

        $result = $engine->getCompletions(new CompletionRequest('$', 1));

        $this->assertSame(['only_once'], $result);
    }

    public function testAllCatalogSourceKindsAreRegistered(): void
    {
        $catalog = new SymbolCatalog();
        $engine = new CompletionEngine(new Context(), null, $catalog);
        $engine->registerDefaultSources();

        // Classes are always available in the catalog.
        $result = $engine->getCompletions(new CompletionRequest('stdCl', 5));
        $this->assertContains('stdClass', $result);

        // Functions too.
        $result = $engine->getCompletions(new CompletionRequest('array_ma', 8));
        $this->assertContains('array_map', $result);
    }

    public function testCompletionsReflectContextChanges(): void
    {
        $context = new Context();
        $context->setAll(['foo' => 123]);

        $engine = new CompletionEngine($context);
        $engine->addSource(new VariableSource($context));

        $first = $engine->getCompletions(new CompletionRequest('$f', 2));
        $this->assertContains('foo', $first);

        $context->setAll(['bar' => 456]);

        $second = $engine->getCompletions(new CompletionRequest('$f', 2));
        $this->assertSame([], $second);
    }

    public function testStaticFactoryResultSupportsMemberCompletion(): void
    {
        $engine = new CompletionEngine(new Context());
        $engine->registerDefaultSources();

        $input = '\\Psy\\Test\\Fixtures\\Completion\\CompletionEngineFactoryFixture::create()->for';
        $result = $engine->getCompletions(new CompletionRequest($input, \strlen($input)));

        $this->assertContains('format', $result);
    }

    public function testRequestCursorIsNormalizedToBufferLength(): void
    {
        $context = new Context();
        $context->setAll(['foo' => 123]);

        $engine = new CompletionEngine($context);
        $engine->addSource(new VariableSource($context));

        $result = $engine->getCompletions(new CompletionRequest('$f', 999));

        $this->assertContains('foo', $result);
    }

    public function testCommandArgumentRefinementRoutesToCommandSources(): void
    {
        $engine = new CompletionEngine(new Context());
        $command = new class() extends Command implements CommandArgumentCompletionAware {
            protected function configure(): void
            {
                $this->setName('config')->setDescription('Test command');
            }

            public function getArgumentCompletions(AnalysisResult $analysis): array
            {
                return ['set'];
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

        $engine->addRefiner(new CommandContextRefiner([$command]));
        $engine->addSource(new class() implements SourceInterface {
            public function appliesToKind(int $kinds): bool
            {
                return ($kinds & CompletionKind::COMMAND_ARGUMENT) !== 0;
            }

            public function getCompletions(AnalysisResult $analysis): array
            {
                return ['set'];
            }
        });
        $engine->addSource(new class() implements SourceInterface {
            public function appliesToKind(int $kinds): bool
            {
                return ($kinds & CompletionKind::SYMBOL) !== 0;
            }

            public function getCompletions(AnalysisResult $analysis): array
            {
                return ['SIGINT', 'SCANDIR_SORT_ASCENDING'];
            }
        });

        $result = $engine->getCompletions(new CompletionRequest('config s', 8));

        $this->assertSame(['set'], $result);
    }

    public function testCommandArgumentRefinementKeepsHandledEmptyResultsEmpty(): void
    {
        $engine = new CompletionEngine(new Context());
        $command = new class() extends Command implements CommandArgumentCompletionAware {
            protected function configure(): void
            {
                $this->setName('config')->setDescription('Test command');
            }

            public function getArgumentCompletions(AnalysisResult $analysis): array
            {
                return [];
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

        $engine->addRefiner(new CommandContextRefiner([$command]));
        $engine->addSource(new class() implements SourceInterface {
            public function appliesToKind(int $kinds): bool
            {
                return ($kinds & CompletionKind::COMMAND_ARGUMENT) !== 0;
            }

            public function getCompletions(AnalysisResult $analysis): array
            {
                return [];
            }
        });
        $engine->addSource(new class() implements SourceInterface {
            public function appliesToKind(int $kinds): bool
            {
                return ($kinds & CompletionKind::SYMBOL) !== 0;
            }

            public function getCompletions(AnalysisResult $analysis): array
            {
                return ['SIGINT', 'SCANDIR_SORT_ASCENDING'];
            }
        });

        $result = $engine->getCompletions(new CompletionRequest('config list ', 12));

        $this->assertSame([], $result);
    }

    public function testConfigSetPartialValueKeepsEnumeratedValueCompletions(): void
    {
        $engine = new CompletionEngine(new Context());
        $command = new \Psy\Command\ConfigCommand();
        $command->setConfiguration(new \Psy\Configuration([
            'configFile'   => \dirname(__DIR__).'/Fixtures/empty.php',
            'configDir'    => \sys_get_temp_dir(),
            'dataDir'      => \sys_get_temp_dir(),
            'runtimeDir'   => \sys_get_temp_dir(),
            'trustProject' => false,
        ]));

        $engine->registerDefaultSources();
        $engine->addRefiner(new CommandContextRefiner([$command]));
        $engine->addSource(new \Psy\Completion\Source\CommandArgumentSource([$command]));

        $result = $engine->getCompletions(new CompletionRequest('config set verbosity d', 22));

        $this->assertContains('debug', $result);
    }

    public function testConfigExpressionValueFallsBackToGenericPhpCompletion(): void
    {
        $engine = new CompletionEngine(new Context());
        $command = new \Psy\Command\ConfigCommand();
        $command->setConfiguration(new \Psy\Configuration([
            'configFile'   => \dirname(__DIR__).'/Fixtures/empty.php',
            'configDir'    => \sys_get_temp_dir(),
            'dataDir'      => \sys_get_temp_dir(),
            'runtimeDir'   => \sys_get_temp_dir(),
            'trustProject' => false,
        ]));

        $engine->registerDefaultSources();
        $engine->addRefiner(new CommandContextRefiner([$command]));
        $engine->addSource(new \Psy\Completion\Source\CommandArgumentSource([$command]));

        $result = $engine->getCompletions(new CompletionRequest('config set errorLoggingLevel E_', 31));

        $this->assertContains('E_ALL', $result);
    }

    public function testConfigCustomCommandValueFallsBackToGenericPhpCompletion(): void
    {
        $engine = new CompletionEngine(new Context());
        $command = new \Psy\Command\ConfigCommand();
        $command->setConfiguration(new \Psy\Configuration([
            'configFile'   => \dirname(__DIR__).'/Fixtures/empty.php',
            'configDir'    => \sys_get_temp_dir(),
            'dataDir'      => \sys_get_temp_dir(),
            'runtimeDir'   => \sys_get_temp_dir(),
            'trustProject' => false,
        ]));

        $engine->registerDefaultSources();
        $engine->addRefiner(new CommandContextRefiner([$command]));
        $engine->addSource(new \Psy\Completion\Source\CommandArgumentSource([$command]));

        $result = $engine->getCompletions(new CompletionRequest('config set clipboardCommand pb', 30));

        $this->assertContains('PHP_BINARY', $result);
    }
}
