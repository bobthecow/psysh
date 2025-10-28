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

use Psy\Command\HistoryCommand;
use Psy\Output\PassthruPager;
use Psy\Output\ShellOutput;
use Psy\Readline\Readline;
use Psy\Shell;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @group isolation-fail
 */
class HistoryCommandTest extends \Psy\Test\TestCase
{
    private function getHistory(): array
    {
        return [
            0  => 'echo "first"',
            1  => 'echo "bacon"',
            2  => 'echo "third"',
            3  => 'echo "bacon again"',
            4  => 'echo "fifth"',
            5  => 'echo "bacon third time"',
            6  => 'echo "seventh"',
            7  => 'echo "eighth"',
            8  => 'echo "ninth"',
            9  => 'echo "bacon fourth time"',
            10 => 'history', // This will be popped
        ];
    }

    private function createCommand(): HistoryCommand
    {
        $readline = $this->getMockBuilder(Readline::class)->getMock();
        $readline->method('listHistory')->willReturn($this->getHistory());

        $command = new HistoryCommand();
        $command->setReadline($readline);
        $command->setApplication(new Shell());

        return $command;
    }

    private function executeCommand(HistoryCommand $command, array $options): string
    {
        $input = new ArrayInput($options);

        $stream = \fopen('php://memory', 'w+');
        $streamOutput = new StreamOutput($stream);
        $output = new ShellOutput(
            ShellOutput::VERBOSITY_NORMAL,
            false,
            null,
            new PassthruPager($streamOutput)
        );

        $command->run($input, $output);

        \rewind($stream);
        $result = \stream_get_contents($stream);
        \fclose($stream);

        return $result;
    }

    public function testShowAllHistory()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, []);

        // Should show all items except the current 'history' command
        $this->assertStringContainsString('first', $output);
        $this->assertStringContainsString('bacon', $output);
        $this->assertStringContainsString('ninth', $output);
    }

    public function testTailWithoutGrep()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--tail' => '3']);

        // Should show last 3 items (lines 7, 8, 9)
        $this->assertStringContainsString('eighth', $output);
        $this->assertStringContainsString('ninth', $output);
        $this->assertStringContainsString('bacon fourth time', $output);
        $this->assertStringNotContainsString('first', $output);
    }

    public function testHeadWithoutGrep()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--head' => '3']);

        // Should show first 3 items (lines 0, 1, 2)
        $this->assertStringContainsString('first', $output);
        $this->assertStringContainsString('bacon', $output);
        $this->assertStringContainsString('third', $output);
        $this->assertStringNotContainsString('ninth', $output);
    }

    public function testShowRange()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--show' => '5..7']);

        // Should show lines 5, 6, 7
        $this->assertStringContainsString('bacon third time', $output);
        $this->assertStringContainsString('seventh', $output);
        $this->assertStringContainsString('eighth', $output);
        $this->assertStringNotContainsString('first', $output);
        $this->assertStringNotContainsString('ninth', $output);
    }

    public function testGrepWithoutSlice()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--grep' => 'bacon']);

        // Should show all 4 bacon lines (1, 3, 5, 9)
        $this->assertStringContainsString('bacon', $output);
        $this->assertStringNotContainsString('echo "first"', $output);
        $this->assertStringNotContainsString('echo "third"', $output);
        $this->assertStringNotContainsString('echo "fifth"', $output);
        $this->assertStringNotContainsString('echo "seventh"', $output);
    }

    public function testTailWithGrep()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--tail' => '2', '--grep' => 'bacon']);

        // Should filter for bacon first (lines 1, 3, 5, 9), then tail 2
        // Result: lines 5 and 9
        $this->assertStringContainsString('bacon third time', $output);
        $this->assertStringContainsString('bacon fourth time', $output);

        // Should NOT show first two bacon matches
        $this->assertStringNotContainsString('echo "bacon"', $output);
        $this->assertStringNotContainsString('bacon again', $output);
    }

    public function testHeadWithGrep()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--head' => '2', '--grep' => 'bacon']);

        // Should filter for bacon first (lines 1, 3, 5, 9), then head 2
        // Result: lines 1 and 3
        $this->assertStringContainsString('echo "bacon"', $output);
        $this->assertStringContainsString('bacon again', $output);

        // Should NOT show last two bacon matches
        $this->assertStringNotContainsString('bacon third time', $output);
        $this->assertStringNotContainsString('bacon fourth time', $output);
    }

    public function testShowWithGrep()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--show' => '0..5', '--grep' => 'bacon']);

        // Should slice 0..5 first (lines 0-5), then filter for bacon
        // Result: lines 1, 3, 5 (bacon matches within range)
        $this->assertStringContainsString('echo "bacon"', $output);
        $this->assertStringContainsString('bacon again', $output);
        $this->assertStringContainsString('bacon third time', $output);

        // Should NOT show line 9 (outside range)
        $this->assertStringNotContainsString('bacon fourth time', $output);
    }

    public function testLineNumbersArePreserved()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--tail' => '2', '--grep' => 'bacon']);

        // Line numbers should be original positions (5 and 9), not 0 and 1
        $this->assertMatchesRegularExpression('/\b5\b.*bacon third time/', $output);
        $this->assertMatchesRegularExpression('/\b9\b.*bacon fourth time/', $output);
    }

    public function testGrepWithCaseInsensitive()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--grep' => 'BACON', '--insensitive' => true]);

        // Should match bacon case-insensitively
        $this->assertStringContainsString('bacon', $output);
    }

    public function testInvalidArgumentCombinations()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please specify only one of --show, --head, --tail');

        $command = $this->createCommand();
        $this->executeCommand($command, ['--head' => '5', '--tail' => '5']);
    }

    public function testHeadRequiresInteger()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please specify an integer argument for --head');

        $command = $this->createCommand();
        $this->executeCommand($command, ['--head' => 'foo']);
    }

    public function testTailRequiresInteger()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please specify an integer argument for --tail');

        $command = $this->createCommand();
        $this->executeCommand($command, ['--tail' => 'bar']);
    }

    public function testNoNumbersOption()
    {
        $command = $this->createCommand();
        $output = $this->executeCommand($command, ['--head' => '2', '--no-numbers' => true]);

        // Should not show line numbers
        $this->assertStringNotContainsString('0', $output);
        $this->assertStringNotContainsString('1', $output);
    }
}
