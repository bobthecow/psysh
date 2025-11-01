<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ManualUpdater;

use Psy\ManualUpdater\Checker;
use Psy\ManualUpdater\Installer;
use Psy\ManualUpdater\ManualUpdate;
use Psy\VersionUpdater\Downloader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @group isolation-fail
 */
class ManualUpdateTest extends \Psy\Test\TestCase
{
    public function testSuccessWhenManualIsAlreadyLatest()
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects($this->once())
            ->method('isLatest')
            ->willReturn(true);

        $installer = $this->createMock(Installer::class);
        $installer->expects($this->once())
            ->method('isDataDirWritable')
            ->willReturn(true);
        $installer->expects($this->never())
            ->method('install');

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $manualUpdate->run($input, $output);

        $this->assertEquals(ManualUpdate::SUCCESS, $result);
        $this->assertStringContainsString('up-to-date', $output->fetch());
    }

    public function testFailureWhenDataDirectoryNotWritable()
    {
        $checker = $this->createMock(Checker::class);

        $installer = $this->createMock(Installer::class);
        $installer->expects($this->once())
            ->method('isDataDirWritable')
            ->willReturn(false);

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $manualUpdate->run($input, $output);

        $this->assertEquals(ManualUpdate::FAILURE, $result);
        $this->assertStringContainsString('not writable', $output->fetch());
    }

    public function testSuccessfulDownloadAndInstall()
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects($this->once())
            ->method('isLatest')
            ->willReturn(false);
        $checker->expects($this->once())
            ->method('getLatest')
            ->willReturn('3.0.0');
        $checker->expects($this->once())
            ->method('getDownloadUrl')
            ->willReturn('https://example.com/manual.tar.gz');

        $installer = $this->createMock(Installer::class);
        $installer->expects($this->once())
            ->method('isDataDirWritable')
            ->willReturn(true);
        $installer->expects($this->once())
            ->method('install')
            ->willReturn(true);
        $installer->expects($this->once())
            ->method('getInstallPath')
            ->willReturn('/tmp/php_manual.php');

        $downloader = $this->createMock(Downloader::class);
        $downloader->expects($this->once())
            ->method('setTempDir');
        $downloader->expects($this->once())
            ->method('download')
            ->willReturn(true);
        $downloader->expects($this->once())
            ->method('getFilename')
            ->willReturn('/tmp/download.tar.gz');
        $downloader->expects($this->once())
            ->method('cleanup');

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $manualUpdate->setDownloader($downloader);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $manualUpdate->run($input, $output);

        $this->assertEquals(ManualUpdate::SUCCESS, $result);
        $outputText = $output->fetch();
        $this->assertStringContainsString('Downloading', $outputText);
        $this->assertStringContainsString('3.0.0', $outputText);
        $this->assertStringContainsString('Installed', $outputText);
    }

    public function testFailureWhenDownloadFails()
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects($this->once())
            ->method('isLatest')
            ->willReturn(false);
        $checker->expects($this->once())
            ->method('getLatest')
            ->willReturn('3.0.0');
        $checker->expects($this->once())
            ->method('getDownloadUrl')
            ->willReturn('https://example.com/manual.tar.gz');

        $installer = $this->createMock(Installer::class);
        $installer->expects($this->once())
            ->method('isDataDirWritable')
            ->willReturn(true);

        $downloader = $this->createMock(Downloader::class);
        $downloader->expects($this->once())
            ->method('download')
            ->willReturn(false);
        $downloader->expects($this->once())
            ->method('cleanup');

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $manualUpdate->setDownloader($downloader);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $manualUpdate->run($input, $output);

        $this->assertEquals(ManualUpdate::FAILURE, $result);
        $this->assertStringContainsString('failed', \strtolower($output->fetch()));
    }

    public function testFailureWhenInstallFails()
    {
        $checker = $this->createMock(Checker::class);
        $checker->expects($this->once())
            ->method('isLatest')
            ->willReturn(false);
        $checker->expects($this->once())
            ->method('getLatest')
            ->willReturn('3.0.0');
        $checker->expects($this->once())
            ->method('getDownloadUrl')
            ->willReturn('https://example.com/manual.tar.gz');

        $installer = $this->createMock(Installer::class);
        $installer->expects($this->once())
            ->method('isDataDirWritable')
            ->willReturn(true);
        $installer->expects($this->once())
            ->method('install')
            ->willReturn(false);

        $downloader = $this->createMock(Downloader::class);
        $downloader->expects($this->once())
            ->method('download')
            ->willReturn(true);
        $downloader->expects($this->once())
            ->method('getFilename')
            ->willReturn('/tmp/download.tar.gz');
        $downloader->expects($this->once())
            ->method('cleanup');

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $manualUpdate->setDownloader($downloader);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $manualUpdate->run($input, $output);

        $this->assertEquals(ManualUpdate::FAILURE, $result);
        $this->assertStringContainsString('Failed to install', $output->fetch());
    }

    public function testSetDownloaderWorks()
    {
        $checker = $this->createMock(Checker::class);
        $installer = $this->createMock(Installer::class);
        $downloader = $this->createMock(Downloader::class);

        $manualUpdate = new ManualUpdate(['checker' => $checker, 'installer' => $installer]);
        $manualUpdate->setDownloader($downloader);

        // If this doesn't throw an exception, the setter worked
        $this->assertInstanceOf(ManualUpdate::class, $manualUpdate);
    }
}
