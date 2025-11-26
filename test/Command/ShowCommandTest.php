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
use Psy\Command\ShowCommand;
use Psy\Context;
use Psy\Exception\RuntimeException;
use Psy\Shell;

/**
 * @group isolation-fail
 */
class ShowCommandTest extends \Psy\Test\TestCase
{
    private ShowCommand $command;
    /** @var Shell&MockObject */
    private Shell $shell;
    private Context $context;
    private CodeCleaner $cleaner;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->cleaner = new CodeCleaner();

        $this->shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['execute', 'getNamespace', 'getBoundClass', 'getBoundObject', 'formatException'])
            ->getMock();

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        $this->command = new ShowCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
        $this->command->setCodeCleaner($this->cleaner);
    }

    public function testConfigure()
    {
        $this->assertEquals('show', $this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getHelp());
    }

    public function testShowClass()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Shell']);

        $output = $tester->getDisplay();

        // Output contains styled code - look for the class definition
        $this->assertStringContainsString('Shell', $output);
        $this->assertStringContainsString('class', $output);
    }

    public function testShowMethod()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::get']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('function', $output);
        $this->assertStringContainsString('get', $output);
    }

    public function testShowFunctionThrowsForBuiltIn()
    {
        // Built-in functions don't have source code available
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'array_map']);
    }

    public function testShowUserDefinedFunction()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\info']);

        $output = $tester->getDisplay();

        // Use a user-defined function from the codebase
        $this->assertStringContainsString('function', $output);
    }

    public function testShowThrowsWhenNoTarget()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $tester = new PsyCommandTester($this->command);
        $tester->execute([]);
    }

    public function testShowThrowsWhenBothTargetAndEx()
    {
        $this->context->setLastException(new \Exception('test'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'DateTime', '--ex' => '1']);
    }

    public function testShowExceptionContext()
    {
        $exception = new \Exception('Test exception');
        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test exception</error>');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['--ex' => null]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Test exception', $output);
        $this->assertStringContainsString('level 1', $output);
    }

    public function testShowExceptionContextWithIndex()
    {
        // Create an exception with a trace
        $exception = new \Exception('Test from helper');
        try {
            $this->helperThatThrows();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test from helper</error>');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['--ex' => '2']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Test from helper', $output);
        $this->assertStringContainsString('level 2', $output);
    }

    private function helperThatThrows()
    {
        throw new \Exception('Test from helper');
    }

    public function testShowExceptionContextWrapsAround()
    {
        $exception = new \Exception('Simple exception');
        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Simple exception</error>');

        $tester = new PsyCommandTester($this->command);
        // Ask for a trace index higher than exists
        $tester->execute(['--ex' => '999']);

        // Should wrap around to level 1
        $this->assertStringContainsString('level 1', $tester->getDisplay());
    }

    public function testShowExceptionContextIncrementsOnRepeat()
    {
        $exception = new \Exception('Exception');
        try {
            $this->helperThatThrows();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Exception</error>');

        $tester = new PsyCommandTester($this->command);

        // First call - level 1
        $tester->execute(['--ex' => null]);
        $this->assertStringContainsString('level 1', $tester->getDisplay());

        // Second call - level 2 (same command instance tracks state)
        $tester->execute(['--ex' => null]);
        $this->assertStringContainsString('level 2', $tester->getDisplay());
    }

    public function testShowExceptionContextResetsOnNewException()
    {
        $exception1 = new \Exception('First exception');
        $this->context->setLastException($exception1);

        $this->shell->method('formatException')
            ->willReturn('<error>Exception</error>');

        $tester = new PsyCommandTester($this->command);

        // First exception
        $tester->execute(['--ex' => null]);
        $this->assertStringContainsString('level 1', $tester->getDisplay());

        // New exception should reset
        $exception2 = new \Exception('Second exception');
        $this->context->setLastException($exception2);

        $tester->execute(['--ex' => null]);
        $this->assertStringContainsString('level 1', $tester->getDisplay());
    }

    public function testSetsCommandScopeVariablesForClass()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Shell']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Shell', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
        $this->assertArrayHasKey('__file', $vars);
        $this->assertArrayHasKey('__dir', $vars);
    }

    public function testSetsCommandScopeVariablesForMethod()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::get']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Context::get', $vars['__method']);
        $this->assertEquals('Psy\\Context', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
    }

    public function testSetsCommandScopeVariablesForException()
    {
        $exception = new \Exception('Test');
        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test</error>');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['--ex' => null]);

        $vars = $this->context->getCommandScopeVariables();
        // Should have file info from the exception location
        $this->assertArrayHasKey('__file', $vars);
        $this->assertArrayHasKey('__dir', $vars);
        $this->assertArrayHasKey('__line', $vars);
    }

    public function testShowConstantThrows()
    {
        // Global constants don't have source code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'PHP_VERSION']);
    }

    public function testShowClassConstantThrows()
    {
        // Class constants from built-in classes don't have source code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'DateTime::ATOM']);
    }

    public function testShowPropertyThrows()
    {
        // Properties don't have separate source code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $tester = new PsyCommandTester($this->command);
        $tester->execute(['target' => 'Psy\\Context::$scopeVariables']);
    }
}
