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
use Psy\Clipboard\ClipboardMethod;
use Psy\CodeCleaner;
use Psy\Command\CopyCommand;
use Psy\Configuration;
use Psy\Context;
use Psy\Shell;
use Psy\Test\Fixtures\Command\PsyCommandTester;
use Psy\Test\TempPaths;
use Symfony\Component\Console\Output\OutputInterface;

class CopyCommandTest extends \Psy\Test\TestCase
{
    private CopyCommand $command;
    /** @var Shell&MockObject */
    private Shell $shell;
    private Context $context;
    private CodeCleaner $cleaner;
    private Configuration $config;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->context = new Context();
        $this->cleaner = new CodeCleaner();
        $this->tempDir = TempPaths::directory('psysh-test-copy-command-', null, 0777);
        $this->config = new Configuration([
            'configFile' => \dirname(__DIR__).'/Fixtures/empty.php',
        ]);
        $this->config->setConfigDir($this->tempDir);
        $this->config->setDataDir($this->tempDir);
        $this->config->setRuntimeDir($this->tempDir);
        $this->shell = $this->getMockBuilder(Shell::class)
            ->setConstructorArgs([$this->config])
            ->setMethods(['execute', 'getNamespace', 'getBoundClass', 'getBoundObject'])
            ->getMock();

        $this->shell->method('getNamespace')->willReturn(null);
        $this->shell->method('getBoundClass')->willReturn(null);
        $this->shell->method('getBoundObject')->willReturn(null);

        $this->command = new CopyCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
        $this->command->setCodeCleaner($this->cleaner);
        $this->command->setConfiguration($this->config);
    }

    public function testConfigure()
    {
        $this->assertSame('copy', $this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getHelp());
    }

    public function testCopyUsesLastReturnValueWhenNoExpressionIsGiven()
    {
        $clipboard = new RecordingClipboardMethod();
        $this->context->setReturnValue(['name' => 'psysh']);
        $this->config->setClipboard($clipboard);

        $tester = new PsyCommandTester($this->command);

        $this->assertSame(0, $tester->execute([]));
        $this->assertSame([\var_export(['name' => 'psysh'], true)], $clipboard->copied);
        $this->assertStringContainsString('Copied to clipboard.', $tester->getDisplay());
    }

    public function testCopySetsCommandScopeVariablesForObjects()
    {
        $clipboard = new RecordingClipboardMethod();
        $value = new Context();

        $this->shell->method('execute')->willReturn($value);
        $this->config->setClipboard($clipboard);

        $tester = new PsyCommandTester($this->command);
        $this->assertSame(0, $tester->execute(['expression' => 'new Psy\\Context()']));

        $vars = $this->context->getCommandScopeVariables();
        $this->assertSame('Psy\\Context', $vars['__class']);
        $this->assertSame('Psy', $vars['__namespace']);
    }

    public function testCopyFailureShowsErrorAndReturnsNonZero()
    {
        $clipboard = new RecordingClipboardMethod(false);
        $this->context->setReturnValue('value');
        $this->config->setClipboard($clipboard);

        $tester = new PsyCommandTester($this->command);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Unable to copy value to clipboard.', $tester->getDisplay());
    }

    public function testCopyWarnsOnceForCircularReferences()
    {
        $clipboard = new RecordingClipboardMethod();
        $value = new \stdClass();
        $value->self = $value;

        $this->context->setReturnValue($value);
        $this->config->setClipboard($clipboard);

        $tester = new PsyCommandTester($this->command);

        $this->assertSame(0, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Value contains circular references; copied export may be incomplete.', $display);
        $this->assertSame(1, \substr_count($display, 'Value contains circular references; copied export may be incomplete.'));
    }
}

class RecordingClipboardMethod implements ClipboardMethod
{
    /** @var string[] */
    public array $copied = [];
    private bool $result;

    public function __construct(bool $result = true)
    {
        $this->result = $result;
    }

    public function copy(string $text, OutputInterface $output): bool
    {
        $this->copied[] = $text;

        return $this->result;
    }
}
