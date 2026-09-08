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

use Psy\Command\TimeitCommand;
use Psy\Configuration;
use Psy\Exception\InterruptException;
use Psy\Shell;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

class TimeitCommandTest extends TestCase
{
    /**
     * @dataProvider throwingCode
     */
    public function testThrowingCodeReportsTiming(string $code, string $exceptionClass)
    {
        $shell = $this->getMockBuilder(Shell::class)
            ->setConstructorArgs([new Configuration(['usePcntl' => false])])
            ->setMethods(['writeReturnValue'])
            ->getMock();
        $shell->setOutput(new BufferedOutput());
        $shell->expects($this->never())->method('writeReturnValue');

        $command = new TimeitCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);

        try {
            $tester->execute(['code' => $code, '--num' => '5']);
            $this->fail('The exception should propagate.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertSame('test', $e->getMessage());
        }

        $this->assertMatchesRegularExpression('/^Command took \d+\.\d{6} seconds to complete\.\n$/', $tester->getDisplay(true));
    }

    public function throwingCode()
    {
        return [
            ['throw new \\Exception("test")', \Exception::class],
            ['throw new \\Error("test")', \Error::class],
            ['throw new \\Psy\\Exception\\InterruptException("test")', InterruptException::class],
            ['(function () { throw new \\Exception("test"); })()', \Exception::class],
            ['return (function () { throw new \\Exception("test"); })()', \Exception::class],
        ];
    }

    public function testFailedIterationsUseRecordedTimingCount()
    {
        $exception = new \Exception('test');
        $iterations = 0;
        $callback = function () use ($exception, &$iterations) {
            \usleep(1000);
            if (++$iterations === 2) {
                throw $exception;
            }

            return 42;
        };

        $shell = $this->getMockBuilder(Shell::class)
            ->setConstructorArgs([new Configuration(['usePcntl' => false])])
            ->setMethods(['writeReturnValue'])
            ->getMock();
        $shell->setOutput(new BufferedOutput());
        $shell->setScopeVariables(['callback' => $callback]);
        $shell->expects($this->never())->method('writeReturnValue');

        $command = new TimeitCommand();
        $command->setApplication($shell);
        $tester = new CommandTester($command);

        try {
            $tester->execute(['code' => '$callback()', '--num' => '5']);
            $this->fail('The exception should propagate.');
        } catch (\Throwable $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame(2, $iterations);
        $this->assertSame(1, \preg_match('/Command took (\d+\.\d{6}) seconds on average \((\d+\.\d{6}) median; (\d+\.\d{6}) total\) to complete\./', $tester->getDisplay(), $matches));
        $this->assertEqualsWithDelta((float) $matches[3] / 2, (float) $matches[1], 0.000001);
    }

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
        try {
            $tester->execute([
                'code'  => '1 + 1',
                '--num' => '5',
            ]);
        } finally {
            // No timing should be reported if execution never started.
            $this->assertSame('', $tester->getDisplay());
        }

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
