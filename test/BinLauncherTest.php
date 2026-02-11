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

class BinLauncherTest extends TestCase
{
    /** @var string[] */
    private array $tempDirs = [];

    /**
     * @after
     */
    public function cleanupTempDirs(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (!@\is_dir($dir)) {
                continue;
            }

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iter as $path) {
                if ($path->isDir() && !$path->isLink()) {
                    @\rmdir($path->getPathname());
                } else {
                    @\unlink($path->getPathname());
                }
            }

            @\rmdir($dir);
        }
    }

    /**
     * @group isolation-fail
     */
    public function testLauncherDoesNotMarkCurrentProjectProxyAsUntrusted(): void
    {
        $projectDir = $this->makeProjectWithPsyshDependency();
        $result = $this->runLauncher($projectDir, $projectDir.'/vendor/autoload.php');

        $this->assertFalse($result['untrusted']);
        $this->assertSame('false', $result['trust']);
    }

    /**
     * @group isolation-fail
     */
    public function testLauncherMarksDifferentProxyAsUntrusted(): void
    {
        $projectDir = $this->makeProjectWithPsyshDependency();
        $proxyDir = $this->makeTempDir('psysh_proxy_test_');
        @\file_put_contents($proxyDir.'/autoload.php', "<?php\n");

        $result = $this->runLauncher($projectDir, $proxyDir.'/autoload.php');

        $this->assertSame(\str_replace('\\', '/', \realpath($projectDir)), $result['untrusted']);
        $this->assertSame('false', $result['trust']);
    }

    /**
     * @return array Launcher result with 'untrusted' and 'trust' keys
     */
    private function runLauncher(string $projectDir, ?string $proxyAutoload): array
    {
        $runnerDir = $this->makeTempDir('psysh_launcher_runner_');
        $runner = $runnerDir.'/run.php';
        $autoload = \realpath(__DIR__.'/../vendor/autoload.php');
        $bin = \realpath(__DIR__.'/../bin/psysh');

        if ($autoload === false || $bin === false) {
            throw new \RuntimeException('Unable to resolve launcher paths');
        }

        $script = <<<'PHP'
<?php
require $argv[1];

$projectDir = $argv[2];
$proxyAutoload = $argv[3] !== '' ? $argv[3] : null;
$binPath = $argv[4];

unset($_SERVER['PSYSH_UNTRUSTED_PROJECT'], $_SERVER['PSYSH_TRUST_PROJECT']);
unset($_ENV['PSYSH_UNTRUSTED_PROJECT'], $_ENV['PSYSH_TRUST_PROJECT']);
putenv('PSYSH_UNTRUSTED_PROJECT');
putenv('PSYSH_TRUST_PROJECT');

$_SERVER['argv'] = ['psysh', '--no-trust-project'];
$_SERVER['argc'] = 2;

if ($proxyAutoload !== null) {
    $GLOBALS['_composer_autoload_path'] = $proxyAutoload;
} else {
    unset($GLOBALS['_composer_autoload_path']);
}

chdir($projectDir);

ob_start();
include $binPath;
ob_end_clean();

echo json_encode([
    'untrusted' => getenv('PSYSH_UNTRUSTED_PROJECT'),
    'trust' => getenv('PSYSH_TRUST_PROJECT'),
]);
PHP;

        if (@\file_put_contents($runner, $script) === false) {
            throw new \RuntimeException('Unable to write launcher test runner');
        }

        $command = \sprintf(
            '%s %s %s %s %s %s',
            \escapeshellarg(\PHP_BINARY),
            \escapeshellarg($runner),
            \escapeshellarg($autoload),
            \escapeshellarg($projectDir),
            \escapeshellarg($proxyAutoload ?? ''),
            \escapeshellarg($bin)
        );

        $proc = \proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!\is_resource($proc)) {
            throw new \RuntimeException('Unable to launch subprocess');
        }

        @\fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        @\fclose($pipes[1]);
        @\fclose($pipes[2]);

        $exitCode = \proc_close($proc);
        $this->assertSame(0, $exitCode, 'Launcher subprocess failed: '.\trim((string) $stderr));

        $result = \json_decode((string) $stdout, true);
        $this->assertIsArray($result, 'Invalid launcher subprocess output');

        return [
            'untrusted' => $result['untrusted'] ?? false,
            'trust'     => $result['trust'] ?? false,
        ];
    }

    private function makeProjectWithPsyshDependency(): string
    {
        $projectDir = $this->makeTempDir('psysh_bin_launcher_test_');

        @\mkdir($projectDir.'/vendor', 0700, true);
        @\file_put_contents($projectDir.'/vendor/autoload.php', "<?php\n");

        @\file_put_contents($projectDir.'/composer.lock', \json_encode([
            'packages'     => [
                ['name' => 'psy/psysh'],
            ],
            'packages-dev' => [],
        ], \JSON_PRETTY_PRINT)."\n");

        return $projectDir;
    }

    private function makeTempDir(string $prefix): string
    {
        $tmp = \tempnam(\sys_get_temp_dir(), $prefix);
        if ($tmp === false) {
            throw new \RuntimeException('Failed to create temporary path');
        }

        @\unlink($tmp);
        if (!@\mkdir($tmp, 0700, true) && !@\is_dir($tmp)) {
            throw new \RuntimeException('Failed to create temporary directory');
        }

        $this->tempDirs[] = $tmp;

        return $tmp;
    }
}
