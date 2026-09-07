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

    public function testNestedExecutionDoesNotClearOuterInterruptState()
    {
        $shell = $this->getShell();
        $handler = new SignalHandler();
        $wasInterrupted = new \ReflectionProperty(SignalHandler::class, 'wasInterrupted');
        if (\PHP_VERSION_ID < 80100) {
            $wasInterrupted->setAccessible(true);
        }

        $handler->onExecute($shell, 'outer');
        $wasInterrupted->setValue($handler, true);
        $handler->onExecute($shell, 'inner');

        $this->assertTrue($wasInterrupted->getValue($handler));

        $handler->afterExecute($shell);
        $this->assertTrue($wasInterrupted->getValue($handler));
        $handler->afterExecute($shell);
        $this->assertFalse($wasInterrupted->getValue($handler));
    }

    /**
     * @group isolation-fail
     */
    public function testInterruptedDirectExecutionPreservesCallerStdin()
    {
        $code = <<<'PHP'
require $argv[1];

$shell = new Psy\Shell(new Psy\Configuration([
    'configDir' => $argv[2],
    'dataDir' => $argv[2],
    'runtimeDir' => $argv[2],
    'trustProject' => false,
    'usePcntl' => false,
]));
$shell->setOutput(new Symfony\Component\Console\Output\BufferedOutput());
stream_set_blocking(STDIN, false);

try {
    // Invoke the installed signal callback without timing a real signal.
    $shell->execute('(pcntl_signal_get_handler(SIGINT))();', true);
    exit(1);
} catch (Psy\Exception\InterruptException $e) {
}

echo json_encode([
    'blocked' => stream_get_meta_data(STDIN)['blocked'],
    'input' => stream_get_contents(STDIN),
]);
PHP;

        $proc = \proc_open([
            \PHP_BINARY, '-r', $code,
            __DIR__.'/../../vendor/autoload.php',
            TempPaths::reserve('psysh-test-signal-stdin-'),
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        $this->assertIsResource($proc);

        \fwrite($pipes[0], "caller input\n");
        \fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $this->assertSame(0, \proc_close($proc), $stderr);
        $this->assertSame([
            'blocked' => false,
            'input'   => "caller input\n",
        ], \json_decode($stdout, true));
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
