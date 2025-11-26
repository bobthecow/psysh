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

use PHPUnit\Framework\MockObject\MockObject;
use Psy\CodeCleaner;
use Psy\Command\DocCommand;
use Psy\Configuration;
use Psy\Context;
use Psy\Shell;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class DocCommandTest extends \Psy\Test\TestCase
{
    private DocCommand $command;
    /** @var Shell&MockObject */
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
            ->setMethods(['getManualDbFile', 'getDataDir'])
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
}
