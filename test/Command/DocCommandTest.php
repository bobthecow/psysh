<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\CodeCleaner;
use Psy\Command\DocCommand;
use Psy\Configuration;
use Psy\Context;
use Psy\Shell;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A BufferedOutput that supports the paging methods used by ShellOutput.
 */
class DocCommandPageableOutput extends BufferedOutput
{
    public function startPaging()
    {
        // no-op for testing
    }

    public function stopPaging()
    {
        // no-op for testing
    }
}

class DocCommandTest extends \Psy\Test\TestCase
{
    private DocCommand $command;
    private Shell $shell;
    private Context $context;
    private CodeCleaner $cleaner;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->cleaner = new CodeCleaner();

        $this->shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['getNamespace', 'getBoundClass', 'getBoundObject', 'getManual'])
            ->getMock();

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);
        $this->shell->method('getManual')->willReturn(null);

        $this->command = new DocCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
        $this->command->setCodeCleaner($this->cleaner);
    }

    private function executeCommand(array $args = []): string
    {
        $input = new ArrayInput($args);
        $input->bind($this->command->getDefinition());

        $output = new DocCommandPageableOutput();

        $this->command->run($input, $output);

        return $output->fetch();
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
        $output = $this->executeCommand(['target' => 'Psy\\Context']);

        // Should contain the class signature
        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('class', $output);

        // Should contain docblock content
        $this->assertStringContainsString('Shell execution context', $output);
        $this->assertStringContainsString('current variables', $output);
    }

    public function testDocMethod()
    {
        $output = $this->executeCommand(['target' => 'Psy\\Context::get']);

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
        $output = $this->executeCommand(['target' => 'array_map']);

        $this->assertStringContainsString('array_map', $output);
        $this->assertStringContainsString('PHP manual not found', $output);
    }

    public function testDocConstant()
    {
        $output = $this->executeCommand(['target' => 'PHP_VERSION']);

        $this->assertStringContainsString('PHP_VERSION', $output);
    }

    public function testDocClassConstant()
    {
        $output = $this->executeCommand(['target' => 'DateTime::ATOM']);

        $this->assertStringContainsString('ATOM', $output);
    }

    public function testDocProperty()
    {
        $output = $this->executeCommand(['target' => 'Psy\\Context::$scopeVariables']);

        // Should contain both the declaring class and property
        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('scopeVariables', $output);
    }

    public function testDocThrowsWhenNoTarget()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $this->executeCommand([]);
    }

    public function testSetsCommandScopeVariablesForClass()
    {
        $this->executeCommand(['target' => 'Psy\\Shell']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Shell', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
    }

    public function testSetsCommandScopeVariablesForMethod()
    {
        $this->executeCommand(['target' => 'Psy\\Context::get']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Context::get', $vars['__method']);
        $this->assertEquals('Psy\\Context', $vars['__class']);
    }

    public function testDocWithAllFlagShowsParentDocs()
    {
        $outputWithoutAll = $this->executeCommand(['target' => 'Psy\\Exception\\RuntimeException']);
        $this->assertStringContainsString('RuntimeException for Psy', $outputWithoutAll);
        $this->assertStringNotContainsString('---', $outputWithoutAll);

        // With --all, should also include parent class docs
        $outputWithAll = $this->executeCommand(['target' => 'Psy\\Exception\\RuntimeException', '--all' => true]);
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
        $output = $this->executeCommand(['--update-manual' => null]);
        $this->assertStringContainsString('Configuration not available', $output);
    }

    public function testUpdateManualWithConfiguration()
    {
        $config = $this->getMockBuilder(Configuration::class)
            ->setMethods(['getManualDbFile', 'getDataDir'])
            ->getMock();

        $config->method('getManualDbFile')->willReturn(null);
        $config->method('getDataDir')->willReturn(\sys_get_temp_dir());

        $this->command->setConfiguration($config);

        // This will attempt to run the manual update, which will fail
        // in test environment, but we're testing that it gets there
        $output = $this->executeCommand(['--update-manual' => null]);

        // Should either succeed or fail with a reasonable error
        // (not the "Configuration not available" error)
        $this->assertStringNotContainsString('Configuration not available', $output);
    }
}
