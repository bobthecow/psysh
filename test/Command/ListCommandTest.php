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
use Psy\Command\ListCommand;
use Psy\Context;
use Psy\Exception\RuntimeException;
use Psy\Shell;
use Psy\Test\TestCase;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class ListCommandTest extends TestCase
{
    private ListCommand $command;
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->context->setAll(['testVar' => 'value']);

        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['getNamespace', 'getBoundClass', 'getBoundObject'])
            ->getMock();

        $shell->method('getNamespace')->willReturn(null);
        $shell->method('getBoundClass')->willReturn(null);
        $shell->method('getBoundObject')->willReturn(null);

        $this->command = new ListCommand();
        $this->command->setContext($this->context);
        $this->command->setPresenter(new Presenter(new OutputFormatter()));
        $this->command->setCodeCleaner(new CodeCleaner());
        $this->command->setApplication($shell);
    }

    public function testConfigure()
    {
        $this->assertSame('ls', $this->command->getName());
        $this->assertContains('dir', $this->command->getAliases());

        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('target'));
        $this->assertTrue($definition->hasOption('vars'));
        $this->assertTrue($definition->hasOption('constants'));
        $this->assertTrue($definition->hasOption('functions'));
        $this->assertTrue($definition->hasOption('classes'));
        $this->assertTrue($definition->hasOption('interfaces'));
        $this->assertTrue($definition->hasOption('traits'));
        $this->assertTrue($definition->hasOption('properties'));
        $this->assertTrue($definition->hasOption('methods'));
        $this->assertTrue($definition->hasOption('all'));
        $this->assertTrue($definition->hasOption('long'));
        $this->assertTrue($definition->hasOption('globals'));
        $this->assertTrue($definition->hasOption('internal'));
        $this->assertTrue($definition->hasOption('user'));
        $this->assertTrue($definition->hasOption('category'));
        $this->assertTrue($definition->hasOption('no-inherit'));
        $this->assertTrue($definition->hasOption('grep'));
        $this->assertTrue($definition->hasOption('insensitive'));
        $this->assertTrue($definition->hasOption('invert'));
    }

    public function testHelpText()
    {
        $help = $this->command->getHelp();

        $this->assertStringContainsString('ls', $help);
        $this->assertStringContainsString('$foo', $help);
        $this->assertStringContainsString('--grep', $help);
        $this->assertStringContainsString('ReflectionClass', $help);
    }

    public function testPropertiesWithoutTargetThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--properties does not make sense without a specified target');

        $tester = new CommandTester($this->command);
        $tester->execute(['--properties' => true]);
    }

    public function testMethodsWithoutTargetThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--methods does not make sense without a specified target');

        $tester = new CommandTester($this->command);
        $tester->execute(['--methods' => true]);
    }

    public function testNoInheritWithoutTargetThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--no-inherit does not make sense without a specified target');

        $tester = new CommandTester($this->command);
        $tester->execute(['--no-inherit' => true]);
    }

    public function testVarsWithTargetThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--vars does not make sense with a specified target');

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'stdClass', '--vars' => true]);
    }

    public function testGlobalsWithTargetThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--globals does not make sense with a specified target');

        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'stdClass', '--globals' => true]);
    }

    public function testDefaultsToVarsWithoutTarget()
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        // Should list variables by default
        $this->assertStringContainsString('Variable', $output);
        $this->assertStringContainsString('testVar', $output);
    }

    public function testListClasses()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--classes' => true, '--grep' => 'stdClass']);

        $this->assertStringContainsString('stdClass', $tester->getDisplay());
    }

    public function testListFunctions()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--functions' => true, '--grep' => 'array_']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('array_chunk', $output);
        $this->assertStringContainsString('array_combine', $output);
        $this->assertStringContainsString('array_diff', $output);
        $this->assertStringContainsString('array_fill', $output);
        $this->assertStringContainsString('array_flip', $output);
        $this->assertStringContainsString('array_keys', $output);
        $this->assertStringContainsString('array_key_exists', $output);
        $this->assertStringContainsString('array_map', $output);
        $this->assertStringContainsString('array_merge', $output);
        $this->assertStringContainsString('array_pop', $output);
        $this->assertStringContainsString('array_push', $output);
        $this->assertStringContainsString('array_reduce', $output);
        $this->assertStringContainsString('array_reverse', $output);
        $this->assertStringContainsString('array_shift', $output);
        $this->assertStringContainsString('array_sum', $output);
        $this->assertStringContainsString('array_unique', $output);
        $this->assertStringContainsString('array_values', $output);
    }

    public function testListConstants()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--constants' => true, '--grep' => 'PHP_']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('PHP_BINARY', $output);
        $this->assertStringContainsString('PHP_EOL', $output);
        $this->assertStringContainsString('PHP_INT_MAX', $output);
        $this->assertStringContainsString('PHP_INT_MIN', $output);
        $this->assertStringContainsString('PHP_OS', $output);
        $this->assertStringContainsString('PHP_VERSION', $output);
        $this->assertStringContainsString('PHP_VERSION_ID', $output);
    }

    public function testListWithTarget()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['target' => 'DateTime']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Constant', $output);
        $this->assertStringContainsString('ATOM', $output);
        $this->assertStringContainsString('Method', $output);
        $this->assertStringContainsString('getTimestamp', $output);
    }

    public function testListLongFormat()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--classes' => true, '--long' => true, '--grep' => 'stdClass']);

        $this->assertStringContainsString('stdClass', $tester->getDisplay());
    }

    public function testListInterfaces()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--interfaces' => true, '--grep' => 'Iterator']);

        $this->assertStringContainsString('Iterator', $tester->getDisplay());
    }

    public function testListTraits()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--traits' => true]);

        // No traits by default, but it shouldn't explode
        $this->assertIsString($tester->getDisplay());
    }

    public function testListWithInvert()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['--classes' => true, '--grep' => 'stdClass', '--invert' => true]);

        // With invert, stdClass should NOT be in output
        $this->assertStringNotContainsString('stdClass', $tester->getDisplay());
    }
}
