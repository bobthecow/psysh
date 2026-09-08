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

    public function asyncSignalsModes(): array
    {
        return [
            [false],
            [true],
        ];
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runShell(string $mode, string $asyncSignals = '', string $input = ''): array
    {
        $runner = TempPaths::file('psysh-test-process-forker-');
        $directory = TempPaths::directory('psysh-test-process-forker-config-');
        $processForkerPath = (new \ReflectionClass(ProcessForker::class))->getFileName();
        // The test harness loads the PHAR rather than executing it, so Phar::running() is empty.
        $bootstrap = \strpos((string) $processForkerPath, 'phar://') === 0
            ? __DIR__.'/../bootstrap-phar.php'
            : __DIR__.'/../bootstrap.php';

        $script = <<<'PHP'
<?php
if (posix_setsid() === -1) {
    fwrite(STDERR, "Unable to isolate ProcessForker test runner\n");
    exit(1);
}

require $argv[1];

use Psy\Configuration;
use Psy\Exception\BreakException;
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

if ($argv[3] === 'state') {
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
            $this->fail($failure);
        }

        return [
            'stdout'   => (string) $stdout,
            'stderr'   => (string) $stderr,
            'exitCode' => $exitCode,
        ];
    }
}
