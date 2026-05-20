<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive;

use Psy\Output\ShellOutput;
use Psy\Readline\Interactive\TerminalOutput;
use Psy\Test\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class TerminalOutputTest extends TestCase
{
    private static ?\ReflectionProperty $streamOutputStreamProperty = null;

    /** @var resource[] */
    private array $streams = [];

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (\is_resource($stream)) {
                \fclose($stream);
            }
        }

        $this->streams = [];
    }

    public function testTerminalOutputDoesNotMarkShellOutputWritten(): void
    {
        [$output, $stream] = $this->createTestShellOutput();

        $terminalOutput = new TerminalOutput($output);
        $terminalOutput->write('>>> ', false, OutputInterface::OUTPUT_RAW);

        $this->assertFalse($output->consumeVisibleOutputWritten());

        $output->writeln('visible shell output');

        $this->assertTrue($output->consumeVisibleOutputWritten());

        \rewind($stream);
        $this->assertStringContainsString('visible shell output', (string) \stream_get_contents($stream));
    }

    public function testTerminalOutputTracksFormatterChangesOnSourceOutput(): void
    {
        [$output] = $this->createTestShellOutput();
        $terminalOutput = new TerminalOutput($output);

        $formatter = new OutputFormatter(true);
        $output->setFormatter($formatter);

        $this->assertSame($formatter, $terminalOutput->getFormatter());
    }

    /**
     * Create a ShellOutput backed by seekable memory streams.
     *
     * @return array{ShellOutput, resource}
     */
    private function createTestShellOutput(): array
    {
        $stream = \fopen('php://memory', 'w+');
        $errorStream = \fopen('php://memory', 'w+');
        $this->streams[] = $stream;
        $this->streams[] = $errorStream;

        $output = new class($stream, $errorStream) extends ShellOutput {
            private $mainStream;
            private StreamOutput $errorOutput;

            public function __construct($mainStream, $errorStream)
            {
                $this->mainStream = $mainStream;
                $this->errorOutput = new StreamOutput($errorStream, StreamOutput::VERBOSITY_NORMAL, false);
                parent::__construct(StreamOutput::VERBOSITY_NORMAL, false);
            }

            public function getStream()
            {
                return $this->mainStream;
            }

            public function getErrorOutput(): OutputInterface
            {
                return $this->errorOutput;
            }
        };

        $this->setStreamOutputStream($output, $stream);

        return [$output, $stream];
    }

    /**
     * Rebind a StreamOutput instance to a test stream.
     *
     * @param resource $stream
     */
    private function setStreamOutputStream(StreamOutput $output, $stream): void
    {
        if (self::$streamOutputStreamProperty === null) {
            $property = new \ReflectionProperty(StreamOutput::class, 'stream');
            if (\PHP_VERSION_ID < 80100) {
                $property->setAccessible(true);
            }
            self::$streamOutputStreamProperty = $property;
        }

        self::$streamOutputStreamProperty->setValue($output, $stream);
    }
}
