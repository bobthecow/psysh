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
use Psy\VersionUpdater\Installer;

class InstallerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = TempPaths::directory('psysh-test-version-installer-');
    }

    public function testCorruptArchiveIsInvalidAndCannotBeInstalled()
    {
        $archive = $this->tempDir.'/corrupt.tar.gz';
        \file_put_contents($archive, 'not an archive');
        $installer = $this->getInstaller();

        $this->assertFalse($installer->isValidSource($archive));
        $this->assertFalse($installer->install($archive));
        $this->assertSame([], \glob($this->tempDir.'/psysh-*'));
    }

    public function testArchiveWithoutPsyshMemberIsInvalid()
    {
        $archive = $this->tempDir.'/missing-psysh.tar.gz';
        $payload = $this->tempDir.'/README';
        $installLocation = $this->tempDir.'/psysh';
        \file_put_contents($payload, 'not psysh');
        \file_put_contents($installLocation, 'installed version');
        $phar = new \PharData($archive);
        $phar->addFile($payload, 'README');
        $installer = $this->getInstaller();

        $this->assertFalse($installer->isValidSource($archive));
        $this->assertFalse($installer->install($archive));
        $this->assertSame('installed version', \file_get_contents($installLocation));
    }

    public function testArchiveWithPsyshDirectoryIsInvalid()
    {
        $archive = $this->tempDir.'/directory-psysh.tar';
        $phar = new \PharData($archive);
        $phar->addEmptyDir('psysh');
        $installer = $this->getInstaller();

        $this->assertFalse($installer->isValidSource($archive));
        $this->assertFalse($installer->install($archive));
        $this->assertSame([], \glob($this->tempDir.'/psysh-*'));
    }

    private function getInstaller(): Installer
    {
        $installLocation = $this->tempDir.'/psysh';

        return new class($this->tempDir, $installLocation) extends Installer {
            public function __construct(string $tempDirectory, string $installLocation)
            {
                $this->tempDirectory = $tempDirectory;
                $this->installLocation = $installLocation;
            }
        };
    }
}
