<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Output;

use Psy\Output\ShellOutput;
use Psy\Output\ShellOutputAdapter;
use Psy\Readline\Interactive\Pager;
use Psy\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ShellOutputAdapterTest extends TestCase
{
    public function testOutputInterfaceMethodsUseUntypedParametersForSymfony34Compatibility()
    {
        $write = new \ReflectionMethod(ShellOutputAdapter::class, 'write');
        $this->assertFalse($write->getParameters()[1]->hasType());
        $this->assertFalse($write->getParameters()[2]->hasType());

        $writeln = new \ReflectionMethod(ShellOutputAdapter::class, 'writeln');
        $this->assertFalse($writeln->getParameters()[1]->hasType());

        $setVerbosity = new \ReflectionMethod(ShellOutputAdapter::class, 'setVerbosity');
        $this->assertFalse($setVerbosity->getParameters()[0]->hasType());

        $setDecorated = new \ReflectionMethod(ShellOutputAdapter::class, 'setDecorated');
        $this->assertFalse($setDecorated->getParameters()[0]->hasType());
    }

    public function testPageWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->page("one\ntwo");

        $this->assertSame("one\ntwo\n", $output->fetch());
    }

    public function testPageSupportsLineNumbersWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->page([5 => 'alpha', 9 => 'beta'], ShellOutputAdapter::NUMBER_LINES | OutputInterface::OUTPUT_RAW);

        $display = $output->fetch();
        $this->assertStringContainsString("5: alpha\n", $display);
        $this->assertStringContainsString("9: beta\n", $display);
    }

    public function testWritelnSupportsLineNumbersWithBufferedOutput()
    {
        $output = new BufferedOutput();
        $adapter = new ShellOutputAdapter($output);

        $adapter->writeln(['<info>hello</info>'], ShellOutputAdapter::NUMBER_LINES | OutputInterface::OUTPUT_RAW);

        $this->assertStringContainsString("0: <info>hello</info>\n", $output->fetch());
    }

    public function testPageWithPagerUsesDedicatedPagerAndRestoresConfiguredPager(): void
    {
        list($output, $stream) = $this->newShellOutput();

        $pager = $this->createMock(Pager::class);
        $pager->expects($this->once())->method('page')->with(['manual']);

        $adapter = new ShellOutputAdapter($output);
        $adapter->pageWithPager($pager, ['manual']);
        $adapter->page(['default']);

        \rewind($stream);
        $this->assertSame("default\n", \stream_get_contents($stream));
    }

    public function testPageWithPagerRestoresConfiguredPagerAfterRenderThrows(): void
    {
        list($output, $stream) = $this->newShellOutput();

        $pager = $this->createMock(Pager::class);
        $pager->expects($this->once())->method('page')->with(['before error']);

        $adapter = new ShellOutputAdapter($output);
        try {
            $adapter->pageWithPager($pager, function (OutputInterface $pagedOutput): void {
                $pagedOutput->writeln('before error');

                throw new \RuntimeException('render failed');
            });
            $this->fail('Expected render failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('render failed', $e->getMessage());
        }

        $adapter->page(['default']);
        \rewind($stream);
        $this->assertSame("default\n", \stream_get_contents($stream));
    }

    /**
     * @return array{ShellOutput, resource}
     */
    private function newShellOutput(): array
    {
        $stream = \fopen('php://memory', 'w+');
        $errorStream = \fopen('php://memory', 'w+');
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

        return [$output, $stream];
    }
}
