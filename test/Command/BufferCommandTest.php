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
use Psy\Shell;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class BufferCommandTest extends \Psy\Test\TestCase
{
    public function testExecuteWithCommandTester()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getCodeBuffer')->willReturn(['$foo', '$bar']);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());
        $shell->expects($this->never())->method('resetCodeBuffer');

        $command = new BufferCommand();
        $command->setApplication($shell);

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^\s*0:\s+<return>\$foo<\/return>/m', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/^\s*1:\s+<return>\$bar<\/return>/m', $tester->getDisplay());
    }

    public function testExecuteClearWithCommandTester()
    {
        $shell = $this->createMock(Shell::class);
        $shell->method('getCodeBuffer')->willReturn(['$foo']);
        $shell->method('getHelperSet')->willReturn(new HelperSet());
        $shell->method('getDefinition')->willReturn(new InputDefinition());
        $shell->expects($this->once())->method('resetCodeBuffer');

        $command = new BufferCommand();
        $command->setApplication($shell);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--clear' => true]);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/^\s*0:\s+<urgent>\$foo<\/urgent>/m', $tester->getDisplay());
    }
}
