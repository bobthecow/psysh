<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2022 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VersionUpdater;

use Psy\Exception\ErrorException;

class Installer
{
    /**
     * @var string
     */
    protected $installLocation;

    /**
     * @var string
     */
    protected $tempDirectory;

    public function __construct(string $tempDirectory = null)
    {
        $this->tempDirectory = $tempDirectory ?: \sys_get_temp_dir();
        $this->installLocation = \realpath($_SERVER['argv'][0]);
    }

    public function getTempDirectory(): string
    {
        return $this->tempDirectory;
    }

    public function isInstallLocationWritable(): bool
    {
        return \is_writable($this->installLocation);
    }

    public function isTempDirectoryWritable(): bool
    {
        return \is_writable($this->tempDirectory);
    }

    public function isValidSource(string $sourceArchive): bool
    {
        if (!class_exists('\PharData')) {
            return false;
        }
        $pharArchive = new \PharData($sourceArchive);

        return $pharArchive->valid();
    }

    public function install(string $sourceArchive): bool
    {
        $pharArchive = new \PharData($sourceArchive);
        $pharArchive->extractTo($this->tempDirectory, ['psysh'], true);

        return \rename($this->tempDirectory.'/psysh', $this->installLocation);
    }

    public function createBackup(string $version): bool
    {
        $backupFilename = $this->getBackupFilename($version);

        return \rename($this->installLocation, $backupFilename);
    }

    public function restoreFromBackup(string $version): bool
    {
        $backupFilename = $this->getBackupFilename($version);

        if (!\file_exists($backupFilename)) {
            throw new ErrorException("Cannot restore from backup. File not found! [{$backupFilename}]");
        }

        return \rename($backupFilename, $this->installLocation);
    }

    public function getBackupFilename(string $version): string
    {
        $installFilename = \basename($this->installLocation);

        return sprintf('%s/%s.%s', $this->tempDirectory, $installFilename, $version);
    }
}
