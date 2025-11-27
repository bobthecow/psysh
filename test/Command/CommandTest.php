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

use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @group isolation-fail
 */
class CommandTest extends TestCase
{
    public function testSetApplication()
    {
        $command = new TestableCommand();

        // Null is allowed
        $command->setApplication(null);
        $this->assertNull($command->getApplication());

        // Shell is allowed
        $shell = new Shell();
        $command->setApplication($shell);
        $this->assertSame($shell, $command->getApplication());

        // Non-Shell Application throws
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PsySH Commands require an instance of Psy\Shell');
        $command->setApplication(new Application());
    }

    public function testGetShell()
    {
        $command = new TestableCommand();

        // Without shell throws
        try {
            $command->publicGetShell();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('PsySH Commands require an instance of Psy\Shell', $e->getMessage());
        }

        // With shell returns it
        $shell = new Shell();
        $command->setApplication($shell);
        $this->assertSame($shell, $command->publicGetShell());
    }

    public function testGetTable()
    {
        $command = new TestableCommand();
        $output = $this->createMock(OutputInterface::class);

        $this->assertInstanceOf(\Symfony\Component\Console\Helper\Table::class, $command->publicGetTable($output));
    }

    public function testAsText()
    {
        $command = new TestableCommand();
        $command->setAliases(['test-alias', 'ta']);
        $command->setHelp('This is the help text');
        $command->addArgument('name', InputArgument::OPTIONAL, 'The name argument', 'world');
        $command->addArgument('items', InputArgument::IS_ARRAY, 'Array argument', ['foo', 'bar']);
        $command->addOption('flag', 'f', InputOption::VALUE_NONE, 'A flag option');
        $command->addOption('value', null, InputOption::VALUE_REQUIRED, 'A value option', 'default_value');
        $command->addOption('items', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Array option');

        $text = $command->asText();

        // Basic structure
        $this->assertStringContainsString('Usage:', $text);
        $this->assertStringContainsString('testable', $text);

        // Aliases
        $this->assertStringContainsString('Aliases:', $text);
        $this->assertStringContainsString('test-alias', $text);

        // Help
        $this->assertStringContainsString('Help:', $text);
        $this->assertStringContainsString('This is the help text', $text);

        // Arguments with defaults
        $this->assertStringContainsString('Arguments:', $text);
        $this->assertStringContainsString('name', $text);
        $this->assertStringContainsString('The name argument', $text);
        $this->assertStringContainsString('default:', $text);
        $this->assertStringContainsString('world', $text);
        $this->assertStringContainsString("['foo', 'bar']", $text);

        // Options with defaults, shortcuts, and array notation
        $this->assertStringContainsString('Options:', $text);
        $this->assertStringContainsString('--flag', $text);
        $this->assertStringContainsString('(-f)', $text);
        $this->assertStringContainsString('A flag option', $text);
        $this->assertStringContainsString('default_value', $text);
        $this->assertStringContainsString('multiple values allowed', $text);

        // Hidden elements
        $this->assertStringNotContainsString('--verbose', $text);
    }

    public function testAsTextMinimal()
    {
        $command = new TestableCommand();
        $text = $command->asText();

        // Basic command without extras shouldn't show these sections
        $this->assertStringContainsString('Usage:', $text);
        $this->assertStringNotContainsString('Arguments:', $text);
        $this->assertStringNotContainsString('Aliases:', $text);
    }
}
