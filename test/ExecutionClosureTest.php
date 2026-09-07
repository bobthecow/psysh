<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\ExecutionLoop\AbstractListener;
use Psy\ExecutionLoop\ExecutionCleanupListener;
use Psy\ExecutionLoop\Listener;
use Psy\ExecutionLoopClosure;
use Psy\Shell;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ExecutionClosureTest extends TestCase
{
    protected function tearDown(): void
    {
        if (\function_exists('readline_clear_history')) {
            \readline_clear_history();
        }

        parent::tearDown();
    }

    public function testAfterExecuteRunsAfterSuccessfulExecutionIsSettled()
    {
        [$shell, $listener] = $this->getShell();

        $this->assertSame(42, $shell->execute('echo "hello"; $answer = 42; $answer', true));
        $this->assertSame(1, $listener->afterExecuteCalls);
        $this->assertSame(0, $listener->afterLoopCalls);
        $this->assertSame(42, $listener->scopeVariables['answer']);
        $this->assertSame('hello', $listener->scopeVariables['__out']);
        $this->assertSame([false, false], $listener->runActiveStates);
    }

    public function testAfterExecuteRunsAfterFailedExecutionIsCleanedUp()
    {
        [$shell, $listener] = $this->getShell();
        $outputBufferLevel = \ob_get_level();

        try {
            $shell->execute('throw new \RuntimeException("failed")', true);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }

        $this->assertSame(1, $listener->afterExecuteCalls);
        $this->assertSame(0, $listener->afterLoopCalls);
        $this->assertSame($outputBufferLevel, $listener->outputBufferLevel);
        $this->assertSame([false, false], $listener->runActiveStates);
    }

    public function testNestedDirectExecutionPairsExecutionCallbacks()
    {
        [$shell, $listener] = $this->getShell();
        $shell->setScopeVariables(['shell' => $shell]);

        $this->assertSame([1, 2], $shell->execute('return [1, $shell->execute("return 2;", true)];', true));
        $this->assertSame(2, $listener->onExecuteCalls);
        $this->assertSame(2, $listener->afterExecuteCalls);
        $this->assertSame(0, $listener->afterLoopCalls);
    }

    /**
     * Run with piped stdin so simulated terminal state cannot affect the test runner's TTY.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testNestedExecutionKeepsFallbackSignalCharsUntilOuterCleanup()
    {
        [$shell, $listener] = $this->getShell();
        $shell->setScopeVariables(['shell' => $shell]);
        $listener->captureInteractiveSignalChars = true;

        $interactiveSignalCharsEnabled = new \ReflectionProperty(Shell::class, 'interactiveSignalCharsEnabled');
        if (\PHP_VERSION_ID < 80100) {
            $interactiveSignalCharsEnabled->setAccessible(true);
        }
        $interactiveSignalCharsEnabled->setValue($shell, true);

        $this->assertSame([1, 2], $shell->execute('return [1, $shell->execute("return 2;", true)];', true));
        $this->assertSame([true, false], $listener->interactiveSignalCharsStates);
    }

    public function testAfterExecuteOnlyRunsForListenersWhoseOnExecuteCompleted()
    {
        $completed = new ExecutionClosureListener();
        $throwing = new ThrowingExecutionClosureListener();
        $skipped = new ExecutionClosureListener();
        $shell = $this->createShell([$completed, $throwing, $skipped]);

        try {
            $shell->execute('42', true);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }

        $this->assertSame(1, $completed->onExecuteCalls);
        $this->assertSame(1, $completed->afterExecuteCalls);
        $this->assertSame(1, $throwing->onExecuteCalls);
        $this->assertSame(0, $throwing->afterExecuteCalls);
        $this->assertSame(0, $skipped->onExecuteCalls);
        $this->assertSame(0, $skipped->afterExecuteCalls);
    }

    public function testNestedSetupFailureDoesNotCleanUpOuterExecution()
    {
        [$shell, $listener] = $this->getShell();
        $shell->setScopeVariables(['shell' => $shell, 'listener' => $listener]);

        $code = <<<'PHP'
$shell->failFlushCode = true;
try {
    $shell->execute('42', true);
} catch (\RuntimeException $e) {
}
return $listener->afterExecuteCalls;
PHP;
        $result = $shell->execute($code, true);

        $this->assertSame(0, $result);
        $this->assertSame(1, $listener->onExecuteCalls);
        $this->assertSame(1, $listener->afterExecuteCalls);
    }

    public function testAfterExecuteHookIsOptionalForLoopListeners()
    {
        $listener = new LegacyExecutionClosureListener();
        $shell = $this->createShell([$listener]);

        $this->assertSame(42, $shell->execute('21 * 2', true));
        $this->assertSame(1, $listener->onExecuteCalls);
    }

    public function testExecutionLoopPairsExecutionCallbacksWithoutChangingLoopCallbacks()
    {
        [$shell, $listener] = $this->getShell();
        $shell->addInput('42', true);
        $shell->addInput('exit', true);

        $loop = new ExecutionLoopClosure($shell);

        $this->assertSame(0, $loop->execute());
        $this->assertSame(1, $listener->onExecuteCalls);
        $this->assertSame(1, $listener->afterExecuteCalls);
        $this->assertSame(2, $listener->afterLoopCalls);
        $this->assertSame(42, $listener->scopeVariables['_']);
    }

    public function testFullRunOwnsRunStateForItsCompleteLifecycle()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $shell->addInput('21 * 2', true);

        $this->assertSame(0, $shell->doRun(new ArrayInput([]), new BufferedOutput()));
        $this->assertSame([true, true, true, true], $listener->runActiveStates);
        $this->assertFalse($shell->isRunActive());
    }

    public function testFullRunPairsNestedExecutionCallbacksWithoutLoopCallbacks()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $shell->addInput('timeit -n3 1 + 1', true);

        $this->assertSame(0, $shell->doRun(new ArrayInput([]), new BufferedOutput()));
        $this->assertSame(3, $listener->onExecuteCalls);
        $this->assertSame(3, $listener->afterExecuteCalls);
        $this->assertSame(0, $listener->afterLoopCalls);
    }

    public function testNestedFullRunKeepsOuterRunActive()
    {
        [$shell] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $output = new BufferedOutput();
        $shell->setScopeVariables(['shell' => $shell, 'output' => $output]);
        $shell->addInput('[$shell->run(null, $output), $shell->isRunActive()]', true);

        $this->assertSame(0, $shell->doRun(new ArrayInput([]), $output));
        $this->assertSame([0, true], $shell->getScopeVariable('_'));
        $this->assertFalse($shell->isRunActive());
    }

    public function testFullRunReleasesRunStateAfterFailure()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $listener->failBeforeRun = true;

        try {
            $shell->doRun(new ArrayInput([]), new BufferedOutput());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }

        $this->assertSame([true], $listener->runActiveStates);
        $this->assertFalse($shell->isRunActive());
    }

    public function testInteractiveRunSettlesListenersAfterThrowUp()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_FORCED,
        ]);
        $shell->addInput('throw-up new \RuntimeException("failed")', true);

        try {
            $shell->doRun(new ArrayInput([]), new BufferedOutput());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed', $e->getMessage());
        }

        $this->assertSame(1, $listener->afterLoopCalls);
        $this->assertSame(1, $listener->afterRunCalls);
        $this->assertSame([1], $listener->exitCodes);
    }

    public function testInteractiveRunRendersListenerFailuresBeforeTeardown()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_FORCED,
        ]);
        $listener->failBeforeLoop = true;
        $output = new BufferedOutput();

        $this->assertSame(1, $shell->run(null, $output));
        $this->assertSame(1, \substr_count($output->fetch(), 'failed before loop'));
        $this->assertSame(0, $listener->afterLoopCalls);
        $this->assertSame(1, $listener->afterRunCalls);
        $this->assertSame([1], $listener->exitCodes);
    }

    public function testNonInteractiveRunSettlesListenersAfterIncludeFailure()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $shell->failIncludes = true;
        $output = new BufferedOutput();

        $this->assertSame(1, $shell->run(null, $output));
        $this->assertSame(1, \substr_count($output->fetch(), 'failed include'));
        $this->assertSame(0, $listener->afterLoopCalls);
        $this->assertSame(1, $listener->afterRunCalls);
        $this->assertSame([1], $listener->exitCodes);

        $shell->writeException(new BreakException('finished'));
        $this->assertStringContainsString('finished', $output->fetch());
    }

    public function testNonInteractiveRunClearsStateWhenListenerTeardownFails()
    {
        [$shell, $listener] = $this->getShell([
            'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
        ]);
        $listener->failAfterRun = true;
        $shell->addInput('21 * 2', true);
        $output = new BufferedOutput();

        try {
            $shell->doRun(new ArrayInput([]), $output);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed after run', $e->getMessage());
        }

        $output->fetch();
        $shell->writeException(new BreakException('finished'));
        $this->assertStringContainsString('finished', $output->fetch());
    }

    /**
     * @return array{ExecutionClosureTestShell, ExecutionClosureListener}
     */
    private function getShell(array $options = []): array
    {
        $listener = new ExecutionClosureListener();

        return [$this->createShell([$listener], $options), $listener];
    }

    /**
     * @param Listener[] $listeners
     */
    private function createShell(array $listeners, array $options = []): ExecutionClosureTestShell
    {
        $dir = TempPaths::reserve('psysh-test-execution-closure-');
        $config = new Configuration(\array_merge([
            'configDir'    => $dir,
            'dataDir'      => $dir,
            'runtimeDir'   => $dir,
            'trustProject' => false,
        ], $options));
        $shell = new ExecutionClosureTestShell($config, $listeners);
        $shell->setOutput(new BufferedOutput());

        return $shell;
    }
}

class ExecutionClosureListener extends AbstractListener implements ExecutionCleanupListener
{
    public int $afterExecuteCalls = 0;
    public int $afterLoopCalls = 0;
    public int $afterRunCalls = 0;
    public bool $captureInteractiveSignalChars = false;
    public array $exitCodes = [];
    public bool $failAfterRun = false;
    public bool $failBeforeLoop = false;
    public bool $failBeforeRun = false;
    public array $interactiveSignalCharsStates = [];
    public int $onExecuteCalls = 0;
    public ?int $outputBufferLevel = null;
    public array $scopeVariables = [];
    public array $runActiveStates = [];

    public static function isSupported(): bool
    {
        return true;
    }

    public function beforeRun(Shell $shell)
    {
        $this->runActiveStates[] = $shell->isRunActive();

        if ($this->failBeforeRun) {
            throw new \RuntimeException('failed');
        }
    }

    public function beforeLoop(Shell $shell)
    {
        if ($this->failBeforeLoop) {
            throw new \RuntimeException('failed before loop');
        }
    }

    public function onExecute(Shell $shell, string $code)
    {
        $this->onExecuteCalls++;
        $this->runActiveStates[] = $shell->isRunActive();

        return null;
    }

    public function afterExecute(Shell $shell)
    {
        $this->afterExecuteCalls++;
        $this->outputBufferLevel = \ob_get_level();
        $this->scopeVariables = $shell->getScopeVariables();
        $this->runActiveStates[] = $shell->isRunActive();

        if ($this->captureInteractiveSignalChars) {
            $interactiveSignalCharsEnabled = new \ReflectionProperty(Shell::class, 'interactiveSignalCharsEnabled');
            if (\PHP_VERSION_ID < 80100) {
                $interactiveSignalCharsEnabled->setAccessible(true);
            }
            $this->interactiveSignalCharsStates[] = $interactiveSignalCharsEnabled->getValue($shell);
        }
    }

    public function afterLoop(Shell $shell)
    {
        $this->afterLoopCalls++;
        $this->runActiveStates[] = $shell->isRunActive();
    }

    public function afterRun(Shell $shell, int $exitCode = 0)
    {
        $this->afterRunCalls++;
        $this->exitCodes[] = $exitCode;
        $this->runActiveStates[] = $shell->isRunActive();

        if ($this->failAfterRun) {
            throw new \RuntimeException('failed after run');
        }
    }
}

class ThrowingExecutionClosureListener extends ExecutionClosureListener
{
    public function onExecute(Shell $shell, string $code)
    {
        parent::onExecute($shell, $code);

        throw new \RuntimeException('failed');
    }
}

class LegacyExecutionClosureListener implements Listener
{
    public int $onExecuteCalls = 0;

    public static function isSupported(): bool
    {
        return true;
    }

    public function beforeRun(Shell $shell)
    {
    }

    public function beforeLoop(Shell $shell)
    {
    }

    public function onInput(Shell $shell, string $input)
    {
        return null;
    }

    public function onExecute(Shell $shell, string $code)
    {
        $this->onExecuteCalls++;

        return null;
    }

    public function afterLoop(Shell $shell)
    {
    }

    public function afterRun(Shell $shell, int $exitCode = 0)
    {
    }
}

class ExecutionClosureTestShell extends Shell
{
    public bool $failIncludes = false;
    public bool $failFlushCode = false;

    /** @var Listener[] */
    private array $listeners;

    /**
     * @param Listener[] $listeners
     */
    public function __construct(Configuration $config, array $listeners)
    {
        $this->listeners = $listeners;

        parent::__construct($config);
    }

    protected function getDefaultLoopListeners(): array
    {
        return $this->listeners;
    }

    public function flushCode()
    {
        if ($this->failFlushCode) {
            $this->failFlushCode = false;
            throw new \RuntimeException('failed');
        }

        return parent::flushCode();
    }

    public function getIncludes(): array
    {
        if ($this->failIncludes) {
            throw new \RuntimeException('failed include');
        }

        return parent::getIncludes();
    }
}
