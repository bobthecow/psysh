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
use Psy\Command\ShowCommand;
use Psy\Context;
use Psy\Exception\RuntimeException;
use Psy\Shell;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A BufferedOutput that supports the page() method used by ShellOutput.
 */
class PageableOutput extends BufferedOutput
{
    public function page($messages, int $type = 0)
    {
        if (\is_string($messages)) {
            $messages = (array) $messages;
        }

        if (\is_callable($messages)) {
            $messages($this);
        } else {
            $this->write($messages, true, $type);
        }
    }
}

class ShowCommandTest extends \Psy\Test\TestCase
{
    private ShowCommand $command;
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

    private function executeCommand(array $args = []): string
    {
        $input = new ArrayInput($args);
        $input->bind($this->command->getDefinition());

        $output = new PageableOutput();

        $this->command->run($input, $output);

        return $output->fetch();
    }

    public function testConfigure()
    {
        $this->assertEquals('show', $this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getHelp());
    }

    public function testShowClass()
    {
        $output = $this->executeCommand(['target' => 'Psy\\Shell']);

        // Output contains styled code - look for the class definition
        $this->assertStringContainsString('Shell', $output);
        $this->assertStringContainsString('class', $output);
    }

    public function testShowMethod()
    {
        $output = $this->executeCommand(['target' => 'Psy\\Context::get']);

        $this->assertStringContainsString('function', $output);
        $this->assertStringContainsString('get', $output);
    }

    public function testShowFunctionThrowsForBuiltIn()
    {
        // Built-in functions don't have source code available
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $this->executeCommand(['target' => 'array_map']);
    }

    public function testShowUserDefinedFunction()
    {
        // Use a user-defined function from the codebase
        $output = $this->executeCommand(['target' => 'Psy\\info']);

        $this->assertStringContainsString('function', $output);
    }

    public function testShowThrowsWhenNoTarget()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $this->executeCommand([]);
    }

    public function testShowThrowsWhenBothTargetAndEx()
    {
        $this->context->setLastException(new \Exception('test'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments');

        $this->executeCommand(['target' => 'DateTime', '--ex' => '1']);
    }

    public function testShowExceptionContext()
    {
        $exception = new \Exception('Test exception');
        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test exception</error>');

        $output = $this->executeCommand(['--ex' => null]);

        $this->assertStringContainsString('Test exception', $output);
        $this->assertStringContainsString('level 1', $output);
    }

    public function testShowExceptionContextWithIndex()
    {
        // Create an exception with a trace
        try {
            $this->helperThatThrows();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test from helper</error>');

        $output = $this->executeCommand(['--ex' => '2']);

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

        // Ask for a trace index higher than exists
        $output = $this->executeCommand(['--ex' => '999']);

        // Should wrap around to level 1
        $this->assertStringContainsString('level 1', $output);
    }

    public function testShowExceptionContextIncrementsOnRepeat()
    {
        try {
            $this->helperThatThrows();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Exception</error>');

        // First call - level 1
        $output1 = $this->executeCommand(['--ex' => null]);
        $this->assertStringContainsString('level 1', $output1);

        // Second call - level 2 (same command instance tracks state)
        $output2 = $this->executeCommand(['--ex' => null]);
        $this->assertStringContainsString('level 2', $output2);
    }

    public function testShowExceptionContextResetsOnNewException()
    {
        $exception1 = new \Exception('First exception');
        $this->context->setLastException($exception1);

        $this->shell->method('formatException')
            ->willReturn('<error>Exception</error>');

        // First exception
        $output1 = $this->executeCommand(['--ex' => null]);
        $this->assertStringContainsString('level 1', $output1);

        // New exception should reset
        $exception2 = new \Exception('Second exception');
        $this->context->setLastException($exception2);

        $output2 = $this->executeCommand(['--ex' => null]);
        $this->assertStringContainsString('level 1', $output2);
    }

    public function testSetsCommandScopeVariablesForClass()
    {
        $this->executeCommand(['target' => 'Psy\\Shell']);

        $vars = $this->context->getCommandScopeVariables();
        $this->assertEquals('Psy\\Shell', $vars['__class']);
        $this->assertEquals('Psy', $vars['__namespace']);
        $this->assertArrayHasKey('__file', $vars);
        $this->assertArrayHasKey('__dir', $vars);
    }

    public function testSetsCommandScopeVariablesForMethod()
    {
        $this->executeCommand(['target' => 'Psy\\Context::get']);

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

        $this->executeCommand(['--ex' => null]);

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

        $this->executeCommand(['target' => 'PHP_VERSION']);
    }

    public function testShowClassConstantThrows()
    {
        // Class constants from built-in classes don't have source code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $this->executeCommand(['target' => 'DateTime::ATOM']);
    }

    public function testShowPropertyThrows()
    {
        // Properties don't have separate source code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Source code unavailable');

        $this->executeCommand(['target' => 'Psy\\Context::$scopeVariables']);
    }
}
