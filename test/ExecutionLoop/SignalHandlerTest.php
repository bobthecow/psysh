<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ExecutionLoop;

use Psy\Configuration;
use Psy\ExecutionLoop\SignalHandler;
use Psy\Shell;
use Psy\Test\TempPaths;
use Psy\Test\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class SignalHandlerTest extends TestCase
{
    /** @var callable|int */
    private $originalSigintHandler;
    private ?bool $originalAsyncSignals = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!SignalHandler::isSupported()) {
            $this->markTestSkipped('Signal handling is not supported');
        }

        $this->originalSigintHandler = \pcntl_signal_get_handler(\SIGINT);
        $this->originalAsyncSignals = \pcntl_async_signals();
    }

    protected function tearDown(): void
    {
        if ($this->originalAsyncSignals !== null) {
            \pcntl_signal(\SIGINT, $this->originalSigintHandler);
            \pcntl_async_signals($this->originalAsyncSignals);
        }

        if (\function_exists('readline_clear_history')) {
            \readline_clear_history();
        }

        parent::tearDown();
    }

    /**
     * @dataProvider asyncSignalsModes
     */
    public function testDirectExecutionRestoresSignalState(bool $asyncSignals)
    {
        $shell = $this->getShell();
        $handler = static function (): void {
        };
        \pcntl_signal(\SIGINT, $handler);
        \pcntl_async_signals($asyncSignals);

        $this->assertSame(42, $shell->execute('21 * 2', true));

        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    /**
     * @dataProvider asyncSignalsModes
     */
    public function testDirectExecutionRestoresSignalStateAfterException(bool $asyncSignals)
    {
        $shell = $this->getShell();
        $handler = static function (): void {
        };
        \pcntl_signal(\SIGINT, $handler);
        \pcntl_async_signals($asyncSignals);

        try {
            $shell->execute('throw new \RuntimeException("failed")', true);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }

        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    /**
     * @dataProvider asyncSignalsModes
     */
    public function testNestedDirectExecutionKeepsOuterSignalState(bool $asyncSignals)
    {
        $shell = $this->getShell();
        $handler = static function (): void {
        };
        \pcntl_signal(\SIGINT, $handler);
        \pcntl_async_signals($asyncSignals);
        $shell->setScopeVariables(['shell' => $shell]);

        $result = $shell->execute(
            '$installed = \pcntl_signal_get_handler(\SIGINT);'
            .'$shell->execute("return 2;", true);'
            .'return [$installed === \pcntl_signal_get_handler(\SIGINT), \pcntl_async_signals()];',
            true
        );

        $this->assertSame([true, true], $result);
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    /**
     * @dataProvider asyncSignalsModes
     */
    public function testNonInteractiveRunRestoresSignalState(bool $asyncSignals)
    {
        $shell = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $handler = static function (): void {
        };
        \pcntl_signal(\SIGINT, $handler);
        \pcntl_async_signals($asyncSignals);
        $shell->addInput('timeit -n3 1 + 1', true);

        $this->assertSame(0, $shell->doRun(new ArrayInput([]), new BufferedOutput()));
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    public function asyncSignalsModes(): array
    {
        return [
            [false],
            [true],
        ];
    }

    private function getShell(array $options = []): Shell
    {
        $dir = TempPaths::reserve('psysh-test-signal-handler-');
        $config = new Configuration(\array_merge([
            'configDir'    => $dir,
            'dataDir'      => $dir,
            'runtimeDir'   => $dir,
            'trustProject' => false,
            'usePcntl'     => false,
        ], $options));

        $shell = new Shell($config);
        $shell->setOutput(new BufferedOutput());

        return $shell;
    }
}
