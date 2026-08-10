<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Test\TempPaths;
use Psy\Test\TestCase;
use Psy\VersionUpdater\Downloader\FileDownloader;

class DownloaderTest extends TestCase
{
    public function testFileDownloaderStreamsNonEmptyFiles()
    {
        $tempDir = TempPaths::directory('psysh-test-file-downloader-');
        $source = TempPaths::file('psysh-test-download-source-');
        \file_put_contents($source, \str_repeat('download', 10000));
        $downloader = new FileDownloader();
        $downloader->setTempDir($tempDir);

        $this->assertTrue($downloader->download($source));
        $this->assertSame(\hash_file('sha256', $source), \hash_file('sha256', $downloader->getFilename()));

        $downloader->cleanup();
        $this->assertFileDoesNotExist($downloader->getFilename());
    }

    public function testFileDownloaderRejectsEmptyFiles()
    {
        $tempDir = TempPaths::directory('psysh-test-file-downloader-');
        $source = TempPaths::file('psysh-test-download-source-');
        $downloader = new FileDownloader();
        $downloader->setTempDir($tempDir);

        $this->assertFalse($downloader->download($source));
        $downloader->cleanup();
    }

    public function testFactoryFallsBackWhenCurlExecIsDisabled()
    {
        $downloaderPath = (new \ReflectionClass(FileDownloader::class))->getFileName();
        $bootstrap = \strpos((string) $downloaderPath, 'phar://') === 0
            ? __DIR__.'/../bootstrap-phar.php'
            : __DIR__.'/../bootstrap.php';
        $code = 'require '.\var_export($bootstrap, true).'; echo get_class(Psy\\VersionUpdater\\Downloader\\Factory::getDownloader());';
        $command = \escapeshellarg(\PHP_BINARY).' -d disable_functions=curl_exec -r '.\escapeshellarg($code);

        \exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertSame(FileDownloader::class, \implode("\n", $output));
    }
}
