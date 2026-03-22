<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ManualUpdater;

use Psy\ManualUpdater\Installer;
use Psy\Test\TempPaths;

class InstallerTest extends \Psy\Test\TestCase
{
    private $tempDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->tempDir = TempPaths::directory('psysh-test-manual-updater-installer-', null, 0755);
    }

    public function testIsDataDirWritableReturnsTrueForWritableDirectory()
    {
        $installer = new Installer($this->tempDir, 'php');
        $this->assertTrue($installer->isDataDirWritable());
    }

    public function testIsDataDirWritableReturnsFalseForNonExistentDirectory()
    {
        $installer = new Installer($this->tempDir.'/nonexistent', 'php');
        $this->assertFalse($installer->isDataDirWritable());
    }

    public function testIsDataDirWritableReturnsFalseForReadOnlyDirectory()
    {
        if (\function_exists('posix_getuid') && \posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test read-only directory permissions as root');
        }

        $readOnlyDir = $this->tempDir.'/readonly';
        \mkdir($readOnlyDir, 0555);

        $installer = new Installer($readOnlyDir, 'php');
        $this->assertFalse($installer->isDataDirWritable());

        \chmod($readOnlyDir, 0755); // Clean up
    }

    public function testGetInstallPathReturnsCorrectPhpPath()
    {
        $installer = new Installer($this->tempDir, 'php');
        $expected = $this->tempDir.'/php_manual.php';
        $this->assertEquals($expected, $installer->getInstallPath());
    }

    public function testGetInstallPathReturnsCorrectSqlitePath()
    {
        $installer = new Installer($this->tempDir, 'sqlite');
        $expected = $this->tempDir.'/php_manual.sqlite';
        $this->assertEquals($expected, $installer->getInstallPath());
    }

    public function testInstallExtractsPhpManualFromTarball()
    {
        // Create a test tarball
        $tarballPath = $this->createTestTarball('php');

        $installer = new Installer($this->tempDir, 'php');
        $result = $installer->install($tarballPath);

        $this->assertTrue($result);
        $this->assertFileExists($this->tempDir.'/php_manual.php');
        $this->assertStringContainsString('test content', \file_get_contents($this->tempDir.'/php_manual.php'));
    }

    public function testInstallExtractsSqliteManualFromTarball()
    {
        // Create a test tarball
        $tarballPath = $this->createTestTarball('sqlite');

        $installer = new Installer($this->tempDir, 'sqlite');
        $result = $installer->install($tarballPath);

        $this->assertTrue($result);
        $this->assertFileExists($this->tempDir.'/php_manual.sqlite');
    }

    public function testInstallReturnsFalseForNonExistentTarball()
    {
        $installer = new Installer($this->tempDir, 'php');
        $result = $installer->install($this->tempDir.'/nonexistent.tar.gz');

        $this->assertFalse($result);
    }

    public function testInstallOverwritesExistingManual()
    {
        // Create existing manual
        $existingFile = $this->tempDir.'/php_manual.php';
        \file_put_contents($existingFile, 'old content');

        // Install new manual
        $tarballPath = $this->createTestTarball('php');
        $installer = new Installer($this->tempDir, 'php');
        $result = $installer->install($tarballPath);

        $this->assertTrue($result);
        $this->assertStringContainsString('test content', \file_get_contents($existingFile));
        $this->assertStringNotContainsString('old content', \file_get_contents($existingFile));
    }

    private function createTestTarball($format)
    {
        $filename = $format === 'php' ? 'php_manual.php' : 'php_manual.sqlite';
        $content = '<?php return ["test content for '.$format.'"];';

        $tempBuildDir = $this->tempDir.'/build';
        \mkdir($tempBuildDir);

        $manualFile = $tempBuildDir.'/'.$filename;
        \file_put_contents($manualFile, $content);

        $tarballPath = $this->tempDir.'/test-manual.tar.gz';

        // Create tarball
        $phar = new \PharData($tarballPath);
        $phar->buildFromDirectory($tempBuildDir);

        // Clean up build directory
        \unlink($manualFile);
        \rmdir($tempBuildDir);

        return $tarballPath;
    }
}
