<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Exception\ErrorException;
use Psy\Shell;
use Psy\VersionUpdater\Checker;
use Psy\VersionUpdater\Downloader;
use Psy\VersionUpdater\Installer;
use Psy\VersionUpdater\SelfUpdate;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateTest extends \Psy\Test\TestCase
{
    private function getSelfUpdater(Checker $checker, Installer $installer): SelfUpdate
    {
        $selfUpdate = new SelfUpdate($checker, $installer);
        $selfUpdate->setDownloader($this->getMockDownloader());

        return $selfUpdate;
    }

    public function testSuccessWhenCurrentVersionIsLatest()
    {
        $installer = $this->getMockInstaller();
        $checker = $this->getMockChecker(true);
        $output = $this->getMockOutput();

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::SUCCESS, $returnValue);
    }

    public function testFailWhenCurrentVersionIsNotWritable()
    {
        $installer = $this->getMockInstaller(['isInstallLocationWritable']);
        $installer
            ->method('isInstallLocationWritable')
            ->willReturn(false);
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput();

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testFailWhenTempDirectoryIsNotWritable()
    {
        $installer = $this->getMockInstaller(['isTempDirectoryWritable']);
        $installer
            ->method('isTempDirectoryWritable')
            ->willReturn(false);
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput();

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testFailWhenDownloadingThrowsAnException()
    {
        $installer = $this->getMockInstaller();
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput('TestCase Exception');

        $downloader = $this->getMockDownloader(['download']);
        $downloader
            ->method('download')
            ->willThrowException(new ErrorException('TestCase Exception'));

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $selfUpdate->setDownloader($downloader);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testFailWhenDownloadingFails()
    {
        $installer = $this->getMockInstaller();
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput('Download failed.');
        $downloader = $this->getMockDownloader(['download']);
        $downloader
            ->method('download')
            ->willReturn(false);

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $selfUpdate->setDownloader($downloader);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testFailWhenDownloadedArchiveIsNotValid()
    {
        $installer = $this->getMockInstaller(['isValidSource']);
        $installer
            ->method('isValidSource')
            ->willReturn(false);
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput('not a valid archive');

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testFailWhenCreateBackupFails()
    {
        $installer = $this->getMockInstaller(['createBackup']);
        $installer
            ->method('createBackup')
            ->willReturn(false);
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput('Failed to create a backup');

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testBackupIsRestoredWhenInstallFails()
    {
        $installer = $this->getMockInstaller(['install', 'restoreFromBackup']);
        $installer
            ->method('install')
            ->willReturn(false);
        $installer
            ->expects($this->once())
            ->method('restoreFromBackup');
        $checker = $this->getMockChecker();
        $output = $this->getMockOutput();

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $returnValue = $selfUpdate->run($this->getInput(), $output);

        $this->assertEquals(SelfUpdate::FAILURE, $returnValue);
    }

    public function testExceptionIsNotCaughtWhenRestoreFails()
    {
        $this->expectException(ErrorException::class);
        $installer = $this->getMockInstaller(['restoreFromBackup', 'install']);
        $installer
            ->method('install')
            ->willReturn(false);
        $installer
            ->method('restoreFromBackup')
            ->willThrowException(new ErrorException('Uncaught Exception'));

        $checker = $this->getMockChecker();
        $output = $this->getMockOutput();

        $selfUpdate = $this->getSelfUpdater($checker, $installer);
        $selfUpdate->run($this->getInput(), $output);

        $this->fail('Expected ErrorException not thrown');
    }

    private function getInput()
    {
        $input = new ArgvInput([]);
        // build a simple input with options that are used in SelfUpdate
        $input->bind(new InputDefinition([
            new InputOption('verbose', 'v', InputOption::VALUE_NONE),
        ]));

        return $input;
    }

    /**
     * Use the more strict onlyMethods if it's available, otherwise use the deprecated setMethods.
     *
     * @return void
     */
    private function setMockMethods($mockBuilder, array $methods)
    {
        if (\method_exists($mockBuilder, 'onlyMethods')) {
            $mockBuilder->onlyMethods($methods);
        } else {
            $mockBuilder->setMethods($methods);
        }
    }

    private function getMockChecker(bool $isLatest = false, string $version = Shell::VERSION)
    {
        $builder = $this->getMockBuilder(Checker::class);
        $this->setMockMethods($builder, ['getLatest', 'isLatest']);
        $checker = $builder->getMock();
        $checker->method('getLatest')->willReturn($version);
        $checker->method('isLatest')->willReturn($isLatest);

        return $checker;
    }

    private function getMockInstaller(array $skipMethods = [])
    {
        $methods = \get_class_methods(Installer::class);
        $builder = $this->getMockBuilder(Installer::class);
        $this->setMockMethods($builder, $methods);
        $installer = $builder->getMock();

        $skipMethods = \array_merge($skipMethods, ['getTempDirectory', 'getBackupFilename', '__construct']);
        foreach ($methods as $method) {
            if (!\in_array($method, $skipMethods)) {
                $installer->method($method)->willReturn(true);
            }
        }

        return $installer;
    }

    private function getMockDownloader(array $skipMethods = [])
    {
        $methods = \get_class_methods(Downloader::class);
        $builder = $this->getMockBuilder(Downloader::class);
        $this->setMockMethods($builder, $methods);
        $downloader = $builder->getMock();

        $skipMethods = \array_merge($skipMethods, ['getFilename', '__construct']);
        foreach ($methods as $method) {
            if (!\in_array($method, $skipMethods)) {
                $downloader->method($method)->willReturn(true);
            }
        }

        return $downloader;
    }

    private function getMockOutput(string $expectOutput = null)
    {
        $methods = \get_class_methods(OutputInterface::class);
        $builder = $this->getMockBuilder(OutputInterface::class);
        $this->setMockMethods($builder, $methods);
        $output = $builder->getMock();

        if ($expectOutput) {
            $output
                ->expects($this->atLeastOnce())
                ->method('writeln')
                ->with(
                    $this->stringContains($expectOutput)
                );
        }

        return $output;
    }
}
