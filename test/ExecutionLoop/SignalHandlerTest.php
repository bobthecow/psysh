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
use Psy\ExecutionClosure;
use Psy\ExecutionLoop\SignalHandler;
use Psy\Readline\Transient;
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

        $shell->beforeExecute();
        $handler->onExecute($shell, 'outer');
        $wasInterrupted->setValue($handler, true);
        $shell->beforeExecute();
        $handler->onExecute($shell, 'inner');

        $this->assertTrue($wasInterrupted->getValue($handler));

        $shell->afterExecute();
        $this->assertTrue($wasInterrupted->getValue($handler));
        $shell->afterExecute();
        $this->assertFalse($wasInterrupted->getValue($handler));
    }

    /**
     * @dataProvider legacyExecutionModes
     */
    public function testLegacyClosureRetainsCallerOwnedOutputAndErrorHandling(bool $throws, bool $asyncSignals, bool $loop, bool $cleanupLoop)
    {
        $shell = $this->getShell();
        $output = new BufferedOutput();
        $shell->setOutput($output);
        $signalHandler = static function (): void {
        };
        $errorHandler = static function (): void {
        };
        \pcntl_signal(\SIGINT, $signalHandler);
        \pcntl_async_signals($asyncSignals);
        \set_error_handler($errorHandler);
        \ob_start();
        $level = \ob_get_level();

        try {
            for ($i = 0; $i < 3; $i++) {
                try {
                    $result = $this->executeLegacy($shell, 'echo "legacy"; '.($throws ? 'throw new \RuntimeException("failed");' : 'return 42;'), $loop, $cleanupLoop);
                    $this->assertFalse($throws);
                    $this->assertSame(42, $result);
                } catch (\RuntimeException $e) {
                    $this->assertTrue($throws);
                    $this->assertSame('failed', $e->getMessage());
                }

                $this->assertSame($level, \ob_get_level());
                $this->assertSame($signalHandler, \pcntl_signal_get_handler(\SIGINT));
                $this->assertSame($asyncSignals, \pcntl_async_signals());
                $currentErrorHandler = \set_error_handler($errorHandler);
                \restore_error_handler();
                $this->assertSame($errorHandler, $currentErrorHandler);
            }

            $this->assertSame("legacy\nlegacy\nlegacy\n", $output->fetch());
            echo 'caller';
            $this->assertSame('caller', \ob_get_contents());
        } finally {
            \ob_end_clean();
            \restore_error_handler();
        }
    }

    public function legacyExecutionModes(): array
    {
        $modes = [];
        foreach ([false, true] as $throws) {
            foreach ([false, true] as $asyncSignals) {
                foreach ($this->legacyLoopModes() as $name => [$loop, $cleanupLoop]) {
                    $modes[$name.' / throws '.(int) $throws.' / async '.(int) $asyncSignals] = [$throws, $asyncSignals, $loop, $cleanupLoop];
                }
            }
        }

        return $modes;
    }

    public function legacyLoopModes(): array
    {
        return [
            'paired'     => [true, true],
            'unpaired'   => [false, false],
            'unfinished' => [true, false],
        ];
    }

    public function testRepeatedUnpairedLegacyCallsCanBeCleanedUpOnce()
    {
        $shell = $this->getShell();
        $handler = \pcntl_signal_get_handler(\SIGINT);
        $asyncSignals = \pcntl_async_signals();

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(42, eval($shell->onExecute('return 42;')));
        }

        $this->assertIsCallable(\pcntl_signal_get_handler(\SIGINT));
        $shell->afterLoop();
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());

        // A redundant legacy cleanup must not unbalance subsequent executions.
        $shell->afterLoop();
        $this->assertSame(43, $shell->execute('43', true));
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    /**
     * @dataProvider nestedLegacyExecutionModes
     */
    public function testLegacyOuterExecutionKeepsSignalsAcrossNestedExecution(bool $outerLoop, string $innerCode)
    {
        $shell = $this->getShell();
        $handler = \pcntl_signal_get_handler(\SIGINT);
        $asyncSignals = \pcntl_async_signals();
        $shell->setScopeVariables(['legacy' => function (bool $loop, bool $cleanupLoop, bool $throws = false) use ($shell): void {
            try {
                $this->executeLegacy($shell, $throws ? 'throw new \RuntimeException();' : 'return 42;', $loop, $cleanupLoop);
            } catch (\RuntimeException $e) {
            }
        }]);

        $result = $this->executeLegacy(
            $shell,
            '$installed = pcntl_signal_get_handler(SIGINT);'.$innerCode
            .'return [$installed === pcntl_signal_get_handler(SIGINT), pcntl_async_signals()];',
            $outerLoop
        );

        $this->assertSame([true, true], $result);
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    public function nestedLegacyExecutionModes(): array
    {
        $innerExecutions = [
            'execute' => '$__psysh__->execute("return 42;", true);',
            'throw'   => <<<'PHP'
try {
    $__psysh__->execute('throw new \RuntimeException();', true);
} catch (\RuntimeException $e) {
}
PHP,
            'loop' => <<<'PHP'
$__psysh__->addInput('21 * 2', true);
$__psysh__->addInput('exit', true);
(new \Psy\ExecutionLoopClosure($__psysh__))->execute();
PHP,
            'legacy'            => '$legacy = $__psysh__->getScopeVariable("legacy"); $legacy(true, true);',
            'legacy-unpaired'   => '$legacy = $__psysh__->getScopeVariable("legacy"); $legacy(false, false);',
            'legacy-unfinished' => '$legacy = $__psysh__->getScopeVariable("legacy"); $legacy(true, false, true);',
        ];

        $modes = [];
        foreach ([false, true] as $outerLoop) {
            foreach ($innerExecutions as $name => $innerCode) {
                $modes[($outerLoop ? 'loop' : 'unpaired').' / '.$name] = [$outerLoop, $innerCode];
            }
        }

        return $modes;
    }

    /**
     * @dataProvider legacyLoopModes
     */
    public function testLegacyInnerLoopDoesNotCleanUpManagedOuterExecution(bool $loop, bool $cleanupLoop)
    {
        $shell = $this->getShell();
        $handler = \pcntl_signal_get_handler(\SIGINT);
        $asyncSignals = \pcntl_async_signals();
        $shell->setScopeVariables(['legacy' => function () use ($shell, $loop, $cleanupLoop): void {
            $this->executeLegacy($shell, 'return 42;', $loop, $cleanupLoop);
        }]);

        $result = $shell->execute(
            '$installed = pcntl_signal_get_handler(SIGINT); $legacy();'
            .'return [$installed === pcntl_signal_get_handler(SIGINT), pcntl_async_signals()];',
            true
        );

        $this->assertSame([true, true], $result);
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    public function testBoundaryDoesNotReleaseAnUnboundedOuterExecution()
    {
        $shell = $this->getShell();
        $handler = \pcntl_signal_get_handler(\SIGINT);
        $asyncSignals = \pcntl_async_signals();
        $shell->onExecute('return 1;');
        $installed = \pcntl_signal_get_handler(\SIGINT);

        // Even an unmatched afterLoop inside a nested boundary cannot release
        // the legacy resources acquired outside that boundary.
        $this->executeLegacy($shell, '$__psysh__->afterLoop(); return 42;', false);
        $this->assertSame($installed, \pcntl_signal_get_handler(\SIGINT));
        $this->assertTrue(\pcntl_async_signals());

        $shell->afterLoop();
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
    }

    public function testUnfinishedLegacyLoopDoesNotLeaveLoopDepthBehind()
    {
        $shell = $this->getShell();
        $handler = \pcntl_signal_get_handler(\SIGINT);
        $asyncSignals = \pcntl_async_signals();

        $this->executeLegacy($shell, 'return 42;', true, false);
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));

        // A later caller with no loop hooks must still be cleaned up at depth 0.
        $shell->onExecute('return 43;');
        $shell->afterLoop();
        $shell->onExecute('return 44;');
        $this->assertNotSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $shell->afterLoop();
        $this->assertSame($handler, \pcntl_signal_get_handler(\SIGINT));
        $this->assertSame($asyncSignals, \pcntl_async_signals());
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

    /**
     * Model downstream closures which replace PsySH's execution body entirely.
     * Laravel Kit calls loop hooks; Web Tinker 1.10.2 calls neither loop hook.
     *
     * @return mixed
     */
    private function executeLegacy(Shell $shell, string $code, bool $loop = true, bool $cleanupLoop = true)
    {
        $closure = new class($shell, $code, $loop, $cleanupLoop) extends ExecutionClosure {
            public function __construct(Shell $__psysh__, string $code, bool $loop, bool $cleanupLoop)
            {
                $this->setClosure($__psysh__, function () use ($__psysh__, $code, $loop, $cleanupLoop) {
                    if ($loop) {
                        $__psysh__->beforeLoop();
                    }

                    \ob_start([$__psysh__, 'writeStdout'], 1);
                    \set_error_handler([$__psysh__, 'handleError']);

                    try {
                        return eval($__psysh__->onExecute($code));
                    } finally {
                        \restore_error_handler();
                        \ob_end_flush();
                        if ($loop && $cleanupLoop) {
                            $__psysh__->afterLoop();
                        }
                    }
                });
            }
        };

        return $closure->execute();
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
        $readline = $this->getMockBuilder(Transient::class)
            ->onlyMethods(['readline'])
            ->getMock();
        $readline->method('readline')->willReturn(false);
        $config->setReadline($readline);

        $shell = new Shell($config);
        $shell->setOutput(new BufferedOutput());

        return $shell;
    }
}
