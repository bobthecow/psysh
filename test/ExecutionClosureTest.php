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
use Psy\ExecutionLoop\AbstractListener;
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

    /**
     * @return array{Shell, ExecutionClosureListener}
     */
    private function getShell(array $options = []): array
    {
        $dir = TempPaths::reserve('psysh-test-execution-closure-');
        $config = new Configuration(\array_merge([
            'configDir'    => $dir,
            'dataDir'      => $dir,
            'runtimeDir'   => $dir,
            'trustProject' => false,
        ], $options));
        $listener = new ExecutionClosureListener();
        $shell = new ExecutionClosureTestShell($config, $listener);
        $shell->setOutput(new BufferedOutput());

        return [$shell, $listener];
    }
}

class ExecutionClosureListener extends AbstractListener
{
    public int $afterExecuteCalls = 0;
    public int $afterLoopCalls = 0;
    public bool $failBeforeRun = false;
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
    }

    public function afterLoop(Shell $shell)
    {
        $this->afterLoopCalls++;
        $this->runActiveStates[] = $shell->isRunActive();
    }

    public function afterRun(Shell $shell, int $exitCode = 0)
    {
        $this->runActiveStates[] = $shell->isRunActive();
    }
}

class ExecutionClosureTestShell extends Shell
{
    private ExecutionClosureListener $listener;

    public function __construct(Configuration $config, ExecutionClosureListener $listener)
    {
        $this->listener = $listener;

        parent::__construct($config);
    }

    protected function getDefaultLoopListeners(): array
    {
        return [$this->listener];
    }
}
