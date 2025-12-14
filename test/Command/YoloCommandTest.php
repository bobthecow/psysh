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

use Psy\Command\YoloCommand;
use Psy\Readline\Readline;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class YoloCommandTest extends \Psy\Test\TestCase
{
    public function testConfigure()
    {
        $command = new YoloCommand();
        $this->assertEquals('yolo', $command->getName());
        $this->assertStringContainsString('bypass', $command->getDescription());
    }

    public function testExecuteWithCode()
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['addCode', 'setForceReload'])
            ->getMock();

        $shell->expects($this->once())
            ->method('addCode')
            ->with('echo "test"');

        $shell->expects($this->exactly(2))
            ->method('setForceReload')
            ->withConsecutive(
                [true],
                [false]
            );

        $command = $this->createCommand($shell, ['echo "test"', 'yolo echo "test"']);
        $tester = new CommandTester($command);
        $tester->execute(['code' => 'echo "test"']);
    }

    public function testExecuteWithBangBang()
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['addCode', 'setForceReload'])
            ->getMock();

        // Should execute the previous command (echo "second"), not the yolo itself
        $shell->expects($this->once())
            ->method('addCode')
            ->with('echo "second"');

        $history = [
            'echo "first"',
            'echo "second"',
            'yolo !!',
        ];

        $command = $this->createCommand($shell, $history);
        $tester = new CommandTester($command);
        $tester->execute(['code' => '!!']);
    }

    public function testExecuteWithBangBangNoHistory()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No previous command to repeat');

        $shell = new Shell();
        $command = $this->createCommand($shell, ['yolo !!']);
        $tester = new CommandTester($command);
        $tester->execute(['code' => '!!']);

        $this->fail();
    }

    public function testForceReloadResetOnException()
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['addCode', 'setForceReload'])
            ->getMock();

        $shell->expects($this->once())
            ->method('addCode')
            ->willThrowException(new \Exception('Parse error'));

        // Force reload should still be reset even on exception
        $shell->expects($this->exactly(2))
            ->method('setForceReload')
            ->withConsecutive(
                [true],
                [false]
            );

        $this->expectException(\Exception::class);

        $command = $this->createCommand($shell, ['bad code', 'yolo bad code']);
        $tester = new CommandTester($command);
        $tester->execute(['code' => 'bad code']);

        $this->fail();
    }

    private function createCommand($shell, array $history): YoloCommand
    {
        $readline = $this->getMockBuilder(Readline::class)->getMock();
        $readline->method('listHistory')->willReturn($history);

        $command = new YoloCommand();
        $command->setReadline($readline);
        $command->setApplication($shell);

        return $command;
    }
}
