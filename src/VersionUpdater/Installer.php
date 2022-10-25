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

    /**
     * Public to allow the Downloader to use the temporary directory if it's been set
     *
     * @return string
     */
    public function getTempDirectory(): string
    {
        return $this->tempDirectory;
    }

    /**
     * Verify the currently installed PsySH phar is writable so it can be replaced
     *
     * @return bool
     */
    public function isInstallLocationWritable(): bool
    {
        return \is_writable($this->installLocation);
    }

    /**
     * Verify the temporary directory is writable so downloads and backups can be saved there
     *
     * @return bool
     */
    public function isTempDirectoryWritable(): bool
    {
        return \is_writable($this->tempDirectory);
    }

    /**
     * Verifies the downloaded archive can be extracted with \PharData
     *
     * @param string $sourceArchive
     *
     * @return bool
     */
    public function isValidSource(string $sourceArchive): bool
    {
        if (!\class_exists('\PharData')) {
            return false;
        }
        $pharArchive = new \PharData($sourceArchive);

        return $pharArchive->valid();
    }

    /**
     * Extract the "psysh" phar from the archive and move it, replacing the currently installed phar
     *
     * @param string $sourceArchive
     *
     * @return bool
     */
    public function install(string $sourceArchive): bool
    {
        $pharArchive = new \PharData($sourceArchive);
        $pharArchive->extractTo($this->tempDirectory, ['psysh'], true);

        return \rename($this->tempDirectory.'/psysh', $this->installLocation);
    }

    /**
     * Create a backup of the currently installed PsySH phar in the temporary directory with a version number postfix
     *
     * @param string $version
     *
     * @return bool
     */
    public function createBackup(string $version): bool
    {
        $backupFilename = $this->getBackupFilename($version);

        return \rename($this->installLocation, $backupFilename);
    }

    /**
     * Restore the backup file to the original PsySH install location
     *
     * @param string $version
     *
     * @throws ErrorException If the backup file could not be found
     *
     * @return bool
     */
    public function restoreFromBackup(string $version): bool
    {
        $backupFilename = $this->getBackupFilename($version);

        if (!\file_exists($backupFilename)) {
            throw new ErrorException("Cannot restore from backup. File not found! [{$backupFilename}]");
        }

        return \rename($backupFilename, $this->installLocation);
    }

    /**
     * Get the full path for the backup target file location
     *
     * @param string $version
     *
     * @return string
     */
    public function getBackupFilename(string $version): string
    {
        $installFilename = \basename($this->installLocation);

        return \sprintf('%s/%s.%s', $this->tempDirectory, $installFilename, $version);
    }
}
