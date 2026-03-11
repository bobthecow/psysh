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
use Psy\Command\WtfCommand;
use Psy\Context;
use Psy\Shell;
use Psy\Test\Fixtures\Command\PsyCommandTester;

class WtfCommandTest extends \Psy\Test\TestCase
{
    private WtfCommand $command;
    /** @var Shell&MockObject */
    private Shell $shell;
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context();

        $this->shell = $this->getMockBuilder(Shell::class)
            ->disableOriginalConstructor()
            ->setMethods(['boot', 'formatException', 'formatExceptionDetails', 'isCompactTheme'])
            ->getMock();

        $this->shell->method('isCompactTheme')->willReturn(false);

        $this->command = new WtfCommand();
        $this->command->setApplication($this->shell);
        $this->command->setContext($this->context);
    }

    public function testWtfIncludesExceptionDetails(): void
    {
        $exception = new \Exception('Test exception');
        $this->context->setLastException($exception);

        $this->shell->method('formatException')
            ->willReturn('<error>Test exception</error>');
        $this->shell->method('formatExceptionDetails')
            ->willReturn("  [\n    \"line\" => 44,\n  ]");

        $tester = new PsyCommandTester($this->command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Test exception', $output);
        $this->assertStringContainsString('"line" => 44', $output);
        $this->assertStringStartsWith("\nTest exception\n", $output);
        $this->assertStringContainsString("\n\n--\n\n", $output);
    }
}
