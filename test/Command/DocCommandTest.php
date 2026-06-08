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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\CodeCleaner;
use Psy\Command\DocCommand;
use Psy\Completion\CompletionEngine;
use Psy\Completion\CompletionRequest;
use Psy\Completion\Refiner\CommandContextRefiner;
use Psy\Completion\Source\CommandArgumentSource;
use Psy\Configuration;
use Psy\Context;
use Psy\Exception\UnexpectedTargetException;
use Psy\Manual\ManualInterface;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class DocCommandTest extends TestCase
{
    private DocCommand $command;
    /** @var Shell&MockObject */
    private Shell $shell;
    private Context $context;
    private CodeCleaner $cleaner;
    private ?ManualInterface $manual = null;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->cleaner = new CodeCleaner();

        $this->shell = $this->getMockBuilder(Shell::class)
            ->onlyMethods(['boot', 'execute', 'getNamespace', 'getBoundClass', 'getBoundObject', 'getManual'])
            ->getMock();

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);
        $this->shell->method('getManual')->willReturnCallback(fn () => $this->manual);

        $this->command = new DocCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
        $this->command->setCodeCleaner($this->cleaner);
    }

    public function testConfigure()
    {
        $this->assertEquals('doc', $this->command->getName());
        $this->assertContains('rtfm', $this->command->getAliases());
        $this->assertContains('man', $this->command->getAliases());
        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getHelp());
    }

    public function testDocClass()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context']);

        $output = $tester->getDisplay();

        // Should contain the class signature
        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('class', $output);

        // Should contain docblock content
        $this->assertStringContainsString('Shell execution context', $output);
        $this->assertStringContainsString('current variables', $output);
    }

    public function testDocMethod()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::get']);

        $output = $tester->getDisplay();

        // Should contain both the declaring class and method signature
        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('get', $output);

        // Should contain docblock content (formatted)
        $this->assertStringContainsString('Get a context variable', $output);
        $this->assertStringContainsString('Throws:', $output);
        $this->assertStringContainsString('InvalidArgumentException', $output);
    }

    public function testDocFunction()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'array_map']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('array_map', $output);
        $this->assertStringContainsString('PHP manual not found', $output);
    }

    /**
     * @dataProvider languageConstructManualEntries
     */
    public function testDocLanguageConstructUsesManualEntry(string $target, string $manualDoc, string $signature)
    {
        $this->manual = $this->getMockBuilder(ManualInterface::class)->getMock();
        $this->manual->method('getVersion')->willReturn(2);
        $this->manual->expects($this->once())
            ->method('get')
            ->with($target)
            ->willReturn($manualDoc);

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => $target]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString($target, $output);
        $this->assertStringContainsString($signature, \strip_tags($output));
        $this->assertStringContainsString($manualDoc, $output);
    }

    public function testDocLanguageConstructFormatsSignature()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'isset']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('isset', $output);
        $this->assertStringContainsString('$var, ...$vars', \strip_tags($output));
    }

    public function testDocManualPageName()
    {
        $this->manual = $this->getMockBuilder(ManualInterface::class)->getMock();
        $this->manual->method('getVersion')->willReturn(3);
        $this->manual->expects($this->once())
            ->method('get')
            ->with('language.types.array')
            ->willReturn([
                'type'        => 'language',
                'description' => 'Array manual page.',
            ]);

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'language.types.array']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Description:', $output);
        $this->assertStringContainsString('Array manual page.', $output);
    }

    public function testDocLanguageConstructSuggestsRelatedManualPages()
    {
        $this->manual = $this->manualWithIds([
            'array' => [
                'type'        => 'function',
                'description' => 'Creates an array.',
            ],
        ], [
            'array',
            'language.operators.array',
            'language.types.array',
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'array']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Creates an array.', $output);
        $this->assertStringContainsString('Related manual pages:', $output);
        $this->assertStringContainsString('language.operators.array', $output);
        $this->assertStringContainsString('language.types.array', $output);
    }

    public function testDocDoesNotSuggestCurrentManualPageAsRelated()
    {
        $this->manual = $this->manualWithIds([
            'array_merge' => [
                'type'        => 'function',
                'description' => 'Merge one or more arrays.',
            ],
        ], [
            'array_merge',
        ]);

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'array_merge']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Merge one or more arrays.', $output);
        $this->assertStringNotContainsString('Related manual pages', $output);
    }

    public function testDocArgumentCompletionIncludesManualPageIds()
    {
        $this->manual = $this->manualWithIds([], [
            'language.operators.array',
            'language.types.array',
        ]);

        $result = $this->completeDocInput('doc language.ty');

        $this->assertContains('language.types.array', $result);
        $this->assertNotContains('language.operators.array', $result);
    }

    public function testDocArgumentCompletionKeepsRuntimeTargetNames()
    {
        $result = $this->completeDocInput('doc array_mer');

        $this->assertContains('array_merge', $result);
    }

    public function testDocArgumentCompletionSkipsMemberExpressions()
    {
        $this->assertSame([], $this->completeDocInput('doc DateTime::'));
    }

    public function testDocUnknownTargetSuggestsManualPageNames()
    {
        $this->manual = $this->manualWithIds([], [
            'language.operators.array',
            'language.types.array',
        ]);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'langauge.types.array']);

        $output = $tester->getDisplay();
        $this->assertSame(1, $status);
        $this->assertStringContainsString('  Unknown target  langauge.types.array', $output);
        $this->assertStringContainsString('Did you mean?', $output);
        $this->assertStringContainsString('doc language.types.array', $output);
        $this->assertStringNotContainsString('doc language.operators.array', $output);
    }

    public function testDocUnknownTargetSuggestsFuzzyManualPageNames()
    {
        $this->manual = $this->manualWithIds([], [
            'language.references.unset',
        ]);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'language.referneces.unset']);

        $output = $tester->getDisplay();
        $this->assertSame(1, $status);
        $this->assertStringContainsString('  Unknown target  language.referneces.unset', $output);
        $this->assertStringContainsString('Did you mean?', $output);
        $this->assertStringContainsString('doc language.references.unset', $output);
    }

    public function testDocUnknownTargetSuggestsFuzzyRuntimeTargetNames()
    {
        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'array_merg']);

        $output = $tester->getDisplay();
        $this->assertSame(1, $status);
        $this->assertStringContainsString('  Unknown target  array_merg', $output);
        $this->assertStringContainsString('Did you mean?', $output);
        $this->assertStringContainsString('doc array_merge', $output);
    }

    public function testDocUnknownTargetSuggestsFuzzyLanguageConstructNames()
    {
        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'isste']);

        $output = $tester->getDisplay();
        $this->assertSame(1, $status);
        $this->assertStringContainsString('  Unknown target  isste', $output);
        $this->assertStringContainsString('Did you mean?', $output);
        $this->assertStringContainsString('doc isset', $output);
    }

    public function testDocUnexpectedTargetDoesNotBecomeSuggestion()
    {
        $this->shell->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $this->expectException(UnexpectedTargetException::class);
        $this->expectExceptionMessage('Unable to inspect a non-object');

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => '$array_merg->foo']);
    }

    public function testDocUnknownTargetSuggestionsRespectCompactTheme()
    {
        $this->command->setConfiguration($this->createConfiguration(['theme' => 'compact']));

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'array_merg']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Unknown target  array_merg', $tester->getDisplay());
        $this->assertStringNotContainsString('  Unknown target  array_merg', $tester->getDisplay());
    }

    public function testDocAdvertisedManualPageThatCannotLoadDoesNotSuggestItself()
    {
        $this->manual = $this->manualWithIds([], [
            'language.types.array',
        ]);

        $tester = new CommandTester($this->command);
        $status = $tester->execute(['target' => 'language.types.array']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('  Manual page exists but could not be loaded  language.types.array', $tester->getDisplay());
    }

    public static function languageConstructManualEntries()
    {
        return [
            ['array', 'Create an array.', '...$values'],
            ['list', 'Assign variables as if they were an array.', '$var, ...$vars'],
        ];
    }

    public function testDocConstant()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'PHP_VERSION']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('PHP_VERSION', $output);
    }

    public function testDocClassConstant()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'DateTime::ATOM']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('ATOM', $output);
    }

    public function testDocProperty()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::$scopeVariables']);

        $output = $tester->getDisplay();

        // Should contain both the declaring class and property
        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('scopeVariables', $output);
    }

    public function testDocThrowsWhenNoTarget()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }

    public function testSetsCommandScopeVariablesForClass()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Shell']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Shell', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
    }

    public function testSetsCommandScopeVariablesForMethod()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::get']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Context::get', $vars['__method']);
        $this->assertEquals('Psy\\Context', $vars['__class']);
    }

    public function testDocWithAllFlagShowsParentDocs()
    {
        $tester = new CommandTester($this->command);

        $tester->execute(['target' => 'Psy\\Exception\\RuntimeException']);
        $outputWithoutAll = $tester->getDisplay();
        $this->assertStringContainsString('RuntimeException for Psy', $outputWithoutAll);
        $this->assertStringNotContainsString('---', $outputWithoutAll);

        // With --all, should also include parent class docs
        $tester->execute(['target' => 'Psy\\Exception\\RuntimeException', '--all' => true]);
        $outputWithAll = $tester->getDisplay();
        $this->assertStringContainsString('RuntimeException for Psy', $outputWithAll);
        $this->assertStringContainsString('interface', $outputWithAll);
        $this->assertStringContainsString('Psy\\Exception\\Exception', $outputWithAll);
        $this->assertStringContainsString('An interface for Psy Exceptions', $outputWithAll);
        $this->assertStringContainsString('---', $outputWithAll);
        $this->assertStringContainsString('class <class>RuntimeException</class> extends <class>Exception</class>', $outputWithAll);
        $this->assertStringContainsString('class <class>Exception</class> implements', $outputWithAll);
    }

    public function testUpdateManualWithoutConfiguration()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--update-manual' => null]);

        $this->assertStringContainsString('Configuration not available', $tester->getDisplay());
    }

    public function testUpdateManualWithConfiguration()
    {
        $config = $this->getMockBuilder(Configuration::class)
            ->onlyMethods(['getManualDbFile', 'getDataDir'])
            ->getMock();

        $config->method('getManualDbFile')->willReturn(null);
        $config->method('getDataDir')->willReturn(\sys_get_temp_dir());

        $this->command->setConfiguration($config);

        $tester = new CommandTester($this->command);
        $tester->execute(['--update-manual' => null]);

        // Should either succeed or fail with a reasonable error
        // (not the "Configuration not available" error)
        $this->assertStringNotContainsString('Configuration not available', $tester->getDisplay());
    }

    private function completeDocInput(string $input): array
    {
        $engine = new CompletionEngine($this->context, $this->cleaner);
        $engine->addRefiner(new CommandContextRefiner([$this->command]));
        $engine->addSource(new CommandArgumentSource([$this->command]));

        return $engine->getCompletions(new CompletionRequest($input, \strlen($input)));
    }

    private function createConfiguration(array $config = []): Configuration
    {
        return new Configuration(\array_merge([
            'configFile'   => \dirname(__DIR__).'/Fixtures/empty.php',
            'trustProject' => false,
        ], $config));
    }

    private function manualWithIds(array $docs, array $ids): ManualInterface
    {
        return new class($docs, $ids) implements ManualInterface {
            private array $docs;
            private array $ids;

            public function __construct(array $docs, array $ids)
            {
                $this->docs = $docs;
                $this->ids = $ids;
            }

            public function get(string $id)
            {
                return $this->docs[$id] ?? null;
            }

            public function getVersion(): int
            {
                return 3;
            }

            public function getMeta(): array
            {
                return [];
            }

            public function getIds(): array
            {
                return $this->ids;
            }
        };
    }
}
