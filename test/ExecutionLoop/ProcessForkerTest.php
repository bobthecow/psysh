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

use Psy\ExecutionLoop\ProcessForker;
use Psy\Test\TempPaths;
use Psy\Test\TestCase;

class ProcessForkerTest extends TestCase
{
    /**
     * @dataProvider requiredPcntlFunctions
     */
    public function testSupportProbeRejectsDisabledPcntlFunction(string $function)
    {
        $code = 'require '.\var_export($this->getBootstrap(), true).'; echo json_encode(['
            .'Psy\\ExecutionLoop\\ProcessForker::isPcntlSupported(), '
            .'Psy\\ExecutionLoop\\ProcessForker::disabledPcntlFunctions()]);';
        $command = \escapeshellarg(\PHP_BINARY).' -d disable_functions='.\escapeshellarg($function).' -r '.\escapeshellarg($code);

        \exec($command, $output, $exitCode);

        [$supported, $disabled] = \json_decode(\implode("\n", $output), true);

        $this->assertSame(0, $exitCode);
        $this->assertFalse($supported);
        $this->assertContains($function, $disabled);
    }

    public function requiredPcntlFunctions(): array
    {
        return [
            ['pcntl_async_signals'],
            ['pcntl_wifexited'],
            ['pcntl_wifsignaled'],
            ['pcntl_wtermsig'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!ProcessForker::isSupported()) {
            $this->markTestSkipped('Process forking is not supported');
        }

        if (!\function_exists('posix_setsid')) {
            $this->markTestSkipped('Process group isolation is not supported');
        }
    }

    /**
     * @dataProvider asyncSignalsModes
     */
    public function testParentRestoresSignalAndShellState(bool $asyncSignals)
    {
        $result = $this->runShell('state', $asyncSignals ? '1' : '0');
        $state = \json_decode($result['stdout'], true);

        $this->assertSame('', $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertIsArray($state);
        $this->assertSame(0, $state['status']);
        $this->assertTrue($state['handlerRestored']);
        $this->assertSame($asyncSignals, $state['asyncSignals']);
        $this->assertTrue($state['exceptionRendered']);
        $this->assertTrue($state['nestedHandlerActive']);
        $this->assertIsInt($state['sessionPid']);
        $this->assertIsInt($state['runnerPid']);
        $this->assertNotSame($state['runnerPid'], $state['sessionPid']);
    }

    public function testPipedCommandFailureIsReported()
    {
        $result = $this->runShell('pipe', '', "doc NoSuchThingAtAll\n");

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString(
            'Unknown namespace, class or function: NoSuchThingAtAll',
            $result['stdout'].$result['stderr']
        );
    }

    /**
     * @dataProvider crashSignals
     */
    public function testSavegameRestoresStateAfterCrashSignal(string $signal)
    {
        $this->requireCoreDumpControl();
        $result = $this->runShell('signal-'.$signal);
        $state = \json_decode($result['stdout'], true);

        $this->assertSame('', $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(['status' => 23, 'value' => 'saved'], $state);
    }

    public function crashSignals(): array
    {
        return [
            ['SIGSEGV'],
            ['SIGBUS'],
            ['SIGABRT'],
            ['SIGILL'],
            ['SIGFPE'],
            ['SIGTRAP'],
        ];
    }

    public function testSavegameRestoresStateAfterErrorExit()
    {
        $result = $this->runShell('recover-exit');
        $state = \json_decode($result['stdout'], true);

        $this->assertSame('', $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(['status' => 23, 'value' => 'saved'], $state);
    }

    /**
     * @dataProvider terminationSignals
     */
    public function testSavegameDoesNotRestoreAfterTerminationSignal(string $signal)
    {
        $this->requireCoreDumpControl();
        $result = $this->runShell('signal-'.$signal);
        $state = \json_decode($result['stdout'], true);

        $this->assertSame('', $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(['status' => 0, 'value' => 'saved'], $state);
    }

    public function terminationSignals(): array
    {
        return [
            ['SIGTERM'],
            ['SIGINT'],
            ['SIGHUP'],
            ['SIGQUIT'],
            ['SIGKILL'],
            ['SIGPIPE'],
            ['SIGUSR1'],
        ];
    }

    /**
     * @dataProvider childExitModes
     */
    public function testParentReportsChildExitStatus(string $mode, int $expectedStatus)
    {
        $result = $this->runShell($mode);
        $state = \json_decode($result['stdout'], true);

        $this->assertSame(\strpos($mode, 'clean-exit') !== false ? "worker shutdown\n" : '', $result['stderr']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(['status' => $expectedStatus, 'value' => 'saved'], $state);
    }

    public function childExitModes(): array
    {
        return [
            ['exit-status', 17],
            ['savegame-clean-exit', 0],
            ['completed-clean-exit', 0],
            ['return-state', 23],
        ];
    }

    public function asyncSignalsModes(): array
    {
        return [
            [false],
            [true],
        ];
    }

    private function getBootstrap(): string
    {
        $processForkerPath = (new \ReflectionClass(ProcessForker::class))->getFileName();

        // The test harness loads the PHAR rather than executing it, so Phar::running() is empty.
        return \strpos((string) $processForkerPath, 'phar://') === 0
            ? __DIR__.'/../bootstrap-phar.php'
            : __DIR__.'/../bootstrap.php';
    }

    private function requireCoreDumpControl(): void
    {
        if (!\function_exists('posix_setrlimit') || !\defined('POSIX_RLIMIT_CORE')) {
            $this->markTestSkipped('Disabling core dumps is not supported');
        }
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runShell(string $mode, string $asyncSignals = '', string $input = ''): array
    {
        $runner = TempPaths::file('psysh-test-process-forker-');
        $directory = TempPaths::directory('psysh-test-process-forker-config-');
        $bootstrap = $this->getBootstrap();

        $script = <<<'PHP'
<?php
if (posix_setsid() === -1) {
    fwrite(STDERR, "Unable to isolate ProcessForker test runner\n");
    exit(1);
}

if (function_exists('uopz_allow_exit')) {
    uopz_allow_exit(true);
}

require $argv[1];

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\ExecutionLoop\ProcessForker;
use Psy\Shell;
use Symfony\Component\Console\Output\BufferedOutput;

$config = new Configuration([
    'configDir'       => $argv[2],
    'dataDir'         => $argv[2],
    'interactiveMode' => Configuration::INTERACTIVE_MODE_DISABLED,
    'rawOutput'       => true,
    'runtimeDir'      => $argv[2],
    'trustProject'    => false,
    'usePcntl'        => true,
]);
$shell = new Shell($config);

$signalMode = strpos($argv[3], 'signal-') === 0;
if ($signalMode || in_array($argv[3], ['recover-exit', 'exit-status', 'savegame-clean-exit', 'completed-clean-exit', 'return-state'], true)) {
    if ($signalMode && !posix_setrlimit(POSIX_RLIMIT_CORE, 0, 0)) {
        fwrite(STDERR, "Unable to disable core dumps\n");
        exit(1);
    }

    $forker = new ProcessForker();
    $shell->setScopeVariables(['value' => 'saved']);

    try {
        $forker->beforeRun($shell);
    } catch (BreakException $e) {
        echo json_encode(['status' => $e->getCode(), 'value' => $shell->getScopeVariable('value')]);
        exit(0);
    }

    if ($signalMode || $argv[3] === 'recover-exit') {
        $forker->beforeLoop($shell);

        if (!file_exists($argv[2].'/failed')) {
            file_put_contents($argv[2].'/failed', '1');
            $shell->setScopeVariables(['value' => 'lost']);
            if (!$signalMode) {
                exit(255);
            }

            $signal = constant(substr($argv[3], strlen('signal-')));
            if ($signal !== SIGKILL) {
                pcntl_signal($signal, SIG_DFL);
            }
            posix_kill(posix_getpid(), $signal);
        }

        $forker->afterLoop($shell);
    } elseif ($argv[3] === 'exit-status') {
        exit(17);
    } elseif (in_array($argv[3], ['savegame-clean-exit', 'completed-clean-exit'], true)) {
        register_shutdown_function(static function (): void {
            fwrite(STDERR, "worker shutdown\n");
        });
        $forker->beforeLoop($shell);
        if ($argv[3] === 'completed-clean-exit') {
            $forker->afterLoop($shell);
        }
        // Code loaded outside the cleaner can terminate the worker directly.
        $shell->setScopeVariables(['quit' => static function (): void {
            exit(0);
        }]);
        $shell->setOutput(new BufferedOutput());
        $shell->execute('$quit();', true);
    }

    $forker->afterRun($shell, 23);
} elseif ($argv[3] === 'state') {
    $output = new BufferedOutput();
    $handler = static function (): void {
    };
    pcntl_signal(SIGINT, $handler);
    pcntl_async_signals($argv[4] === '1');
    $shell->setScopeVariables(['shell' => $shell]);
    $shell->addInput('$shell->execute("return 42;", true); $nestedHandlerActive = is_callable(pcntl_signal_get_handler(SIGINT)); $pid = posix_getpid()', true);

    $status = $shell->run(null, $output);
    $output->fetch();
    $shell->writeException(new BreakException('finished'));

    echo json_encode([
        'status'              => $status,
        'handlerRestored'     => pcntl_signal_get_handler(SIGINT) === $handler,
        'asyncSignals'        => pcntl_async_signals(),
        'exceptionRendered'   => strpos($output->fetch(), 'finished') !== false,
        'nestedHandlerActive' => $shell->getScopeVariable('nestedHandlerActive'),
        'sessionPid'          => $shell->getScopeVariable('pid'),
        'runnerPid'           => posix_getpid(),
    ]);
} else {
    exit($shell->run());
}
PHP;

        if (@\file_put_contents($runner, $script) === false) {
            throw new \RuntimeException('Unable to write ProcessForker test runner');
        }

        $process = \proc_open([
            \PHP_BINARY,
            $runner,
            $bootstrap,
            $directory,
            $mode,
            $asyncSignals,
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!\is_resource($process)) {
            throw new \RuntimeException('Unable to launch ProcessForker test runner');
        }

        $initialStatus = \proc_get_status($process);
        $runnerPid = $initialStatus['pid'];

        \fwrite($pipes[0], $input);
        @\fclose($pipes[0]);

        @\stream_set_blocking($pipes[1], false);
        @\stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $failure = null;
        $deadline = \microtime(true) + 10.0;

        try {
            while (!\feof($pipes[1]) || !\feof($pipes[2])) {
                $remaining = $deadline - \microtime(true);
                if ($remaining <= 0) {
                    $failure = 'ProcessForker test runner timed out';
                    break;
                }

                $read = [];
                if (!\feof($pipes[1])) {
                    $read[] = $pipes[1];
                }
                if (!\feof($pipes[2])) {
                    $read[] = $pipes[2];
                }

                $write = null;
                $except = null;
                $seconds = (int) $remaining;
                $microseconds = (int) (($remaining - $seconds) * 1000000);
                $ready = @\stream_select($read, $write, $except, $seconds, $microseconds);

                if ($ready === false) {
                    $failure = 'Unable to read ProcessForker test runner output';
                    break;
                }

                foreach ($read as $stream) {
                    if ($stream === $pipes[1]) {
                        $stdout .= (string) \stream_get_contents($stream);
                    } else {
                        $stderr .= (string) \stream_get_contents($stream);
                    }
                }
            }
        } finally {
            if ($failure !== null) {
                // Stop the runner first, including if it hasn't reached setsid() yet.
                @\proc_terminate($process, \SIGKILL);
                // Kill any workers remaining in its isolated process group.
                @\posix_kill(-$runnerPid, \SIGKILL);
            }

            @\fclose($pipes[1]);
            @\fclose($pipes[2]);
            $exitCode = \proc_close($process);

            // Older PHP versions can lose an exit code already read by proc_get_status().
            if ($exitCode === -1 && !$initialStatus['running']) {
                $exitCode = $initialStatus['exitcode'];
            }
        }

        if ($failure !== null) {
            $this->fail($failure."\n".\substr($stderr, 0, 2000));
        }

        return [
            'stdout'   => (string) $stdout,
            'stderr'   => (string) $stderr,
            'exitCode' => $exitCode,
        ];
    }
}
