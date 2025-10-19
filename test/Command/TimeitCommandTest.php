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

use Psy\Command\TimeitCommand;
use Psy\Exception\InterruptException;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group isolation-fail
 */
class TimeitCommandTest extends \Psy\Test\TestCase
{
    public function testInterruptStopsMultipleExecutions()
    {
        $this->expectException(InterruptException::class);

        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['execute', 'writeReturnValue'])
            ->getMock();

        // Should only execute once before throwing InterruptException
        $shell->expects($this->once())
            ->method('execute')
            ->with($this->anything(), true)  // throwExceptions = true
            ->willThrowException(new InterruptException());

        // Should never write return value since we're interrupted
        $shell->expects($this->never())
            ->method('writeReturnValue');

        $command = new TimeitCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);

        // Request 5 iterations, but should stop after first one throws
        $tester->execute([
            'code'  => '1 + 1',
            '--num' => '5',
        ]);

        // If we reach this point, execution was not interrupted
        $this->fail();
    }

    public function testSingleExecution()
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['execute', 'writeReturnValue'])
            ->getMock();

        $shell->expects($this->once())
            ->method('execute')
            ->with($this->anything(), true)  // throwExceptions = true
            ->willReturnCallback(function () {
                // Simulate the instrumented code calling markStart/markEnd
                TimeitCommand::markStart();
                $result = 42;

                return TimeitCommand::markEnd($result);
            });

        $shell->expects($this->once())
            ->method('writeReturnValue')
            ->with($this->equalTo(42));

        $command = new TimeitCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);

        $tester->execute(['code' => '1 + 1']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Command took', $output);
        $this->assertStringContainsString('seconds to complete', $output);
    }

    public function testMultipleExecutions()
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setMethods(['execute', 'writeReturnValue'])
            ->getMock();

        $shell->expects($this->exactly(3))
            ->method('execute')
            ->with($this->anything(), true)  // throwExceptions = true
            ->willReturnCallback(function () {
                // Simulate the instrumented code calling markStart/markEnd
                TimeitCommand::markStart();
                $result = 42;

                return TimeitCommand::markEnd($result);
            });

        $shell->expects($this->once())
            ->method('writeReturnValue')
            ->with($this->equalTo(42));

        $command = new TimeitCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);

        $tester->execute([
            'code'  => '1 + 1',
            '--num' => '3',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Command took', $output);
        $this->assertStringContainsString('on average', $output);
        $this->assertStringContainsString('median', $output);
        $this->assertStringContainsString('total', $output);
    }
}
