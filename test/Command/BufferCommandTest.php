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

use Psy\Command\BufferCommand;
use Psy\Readline\LegacyReadline;
use Psy\Readline\Transient;
use Psy\Shell;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

class BufferCommandTest extends \Psy\Test\TestCase
{
    public function testExecuteWithCommandTester()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());

        $readline = $this->getLegacyReadlineWithBuffer(['$foo', '$bar']);

        $command = new BufferCommand();
        $command->setApplication($shell);
        $command->setReadline($readline);

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^\s*0:\s+<return>\$foo<\/return>/m', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/^\s*1:\s+<return>\$bar<\/return>/m', $tester->getDisplay());
    }

    public function testExecuteClearWithCommandTester()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());

        $readline = $this->getLegacyReadlineWithBuffer(['$foo']);

        $command = new BufferCommand();
        $command->setApplication($shell);
        $command->setReadline($readline);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--clear' => true]);

        $this->assertSame(0, $status);
        $this->assertSame([], $readline->getBuffer());
        $this->assertMatchesRegularExpression('/^\s*0:\s+<urgent>\$foo<\/urgent>/m', $tester->getDisplay());
    }

    public function testExecuteReadsAndClearsLegacyReadlineBuffer()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getPendingCodeBuffer')->willReturn([]);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());
        $shell->expects($this->never())->method('clearPendingCodeBuffer');

        $readline = new LegacyReadline(new Transient());
        $readline->setBufferPrompt('... ');
        $readline->setRequireSemicolons(false);

        $bufferProperty = new \ReflectionProperty($readline, 'buffer');
        if (\PHP_VERSION_ID < 80100) {
            $bufferProperty->setAccessible(true);
        }
        $bufferProperty->setValue($readline, ['if (true) {']);

        $command = new BufferCommand();
        $command->setApplication($shell);
        $command->setReadline($readline);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--clear' => true]);

        $this->assertSame(0, $status);
        $this->assertSame([], $readline->getBuffer());
        $this->assertMatchesRegularExpression('/^\s*0:\s+<urgent>if \(true\) \{<\/urgent>/m', $tester->getDisplay());
    }

    public function testExecuteFallsBackToShellPendingBufferWhenLegacyBufferIsEmpty()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getPendingCodeBuffer')->willReturn(['class']);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());
        $shell->expects($this->once())->method('clearPendingCodeBuffer');

        $readline = $this->getLegacyReadlineWithBuffer([]);

        $command = new BufferCommand();
        $command->setApplication($shell);
        $command->setReadline($readline);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--clear' => true]);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^\s*0:\s+<urgent>class<\/urgent>/m', $tester->getDisplay());
    }

    public function testExecuteThrowsIfLegacyReadlineIsMissing()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());

        $command = new BufferCommand();
        $command->setApplication($shell);

        $tester = new CommandTester($command);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('BufferCommand requires LegacyReadline.');

        $tester->execute([]);
    }

    private function getLegacyReadlineWithBuffer(array $buffer): LegacyReadline
    {
        $readline = new LegacyReadline(new Transient());
        $readline->setBufferPrompt('... ');
        $readline->setRequireSemicolons(false);

        $bufferProperty = new \ReflectionProperty($readline, 'buffer');
        if (\PHP_VERSION_ID < 80100) {
            $bufferProperty->setAccessible(true);
        }
        $bufferProperty->setValue($readline, $buffer);

        return $readline;
    }
}
