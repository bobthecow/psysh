<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\ManualUpdater;

use Psy\ConfigPaths;
use Psy\Configuration;
use Psy\Exception\ErrorException;
use Psy\VersionUpdater\Downloader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manual update command.
 *
 * If a new manual version is available, this command will download and install it.
 */
class ManualUpdate
{
    const SUCCESS = 0;
    const FAILURE = 1;

    private Checker $checker;
    private Installer $installer;
    private ?Downloader $downloader = null;

    public function __construct(Checker $checker, Installer $installer)
    {
        $this->checker = $checker;
        $this->installer = $installer;
    }

    /**
     * Create a ManualUpdate instance from Configuration and command-line input.
     *
     * @param Configuration  $config Configuration instance
     * @param InputInterface $input  Input interface
     *
     * @return self
     */
    public static function fromConfig(Configuration $config, InputInterface $input): self
    {
        // Determine language from command line option (or use current/default)
        $lang = $input->getOption('update-manual') ?: null;

        // Clear the manual update cache when explicitly running --update-manual
        $cacheFile = $config->getManualUpdateCheckCacheFile();
        if ($cacheFile && \file_exists($cacheFile)) {
            @\unlink($cacheFile);
        }

        // Get checker (force immediate check for explicit --update-manual command)
        $checker = $config->getManualChecker($lang, true);

        if (!$checker) {
            throw new \RuntimeException('Unable to create manual update checker');
        }

        // Get data directory for manual installation
        $dataDir = $config->getManualInstallDir();
        if ($dataDir === false) {
            throw new \RuntimeException('Unable to find a writable data directory for manual installation');
        }

        // Determine format from current manual file extension, default to v3
        $manualFile = $config->getManualDbFile();
        $format = 'php';
        if ($manualFile && \str_ends_with($manualFile, '.sqlite')) {
            $format = 'sqlite';
        }

        $installer = new Installer($dataDir, $format);
        $manualUpdate = new self($checker, $installer);

        // If using GH CLI, set the custom downloader
        if ($checker instanceof GhChecker || ($checker instanceof IntervalChecker && \shell_exec('which gh 2>/dev/null'))) {
            $manualUpdate->setDownloader(new GhDownloader());
        }

        return $manualUpdate;
    }

    /**
     * Allow the downloader to be injected for testing.
     *
     * @return void
     */
    public function setDownloader(Downloader $downloader)
    {
        $this->downloader = $downloader;
    }

    /**
     * Get the currently set Downloader or create one based on the capabilities of the php environment.
     *
     * @throws ErrorException if a downloader cannot be created for the php environment
     */
    private function getDownloader(): Downloader
    {
        if (!isset($this->downloader)) {
            return Downloader\Factory::getDownloader();
        }

        return $this->downloader;
    }

    /**
     * Execute the manual update process.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        // Already have the latest version?
        if ($this->checker->isLatest()) {
            $output->writeln('<info>Manual is up-to-date.</info>');

            return self::SUCCESS;
        }

        // Can write to data directory?
        if (!$this->installer->isDataDirWritable()) {
            $output->writeln('<error>Data directory is not writable.</error>');

            return self::FAILURE;
        }

        $latestVersion = $this->checker->getLatest();
        $downloadUrl = $this->checker->getDownloadUrl();

        $output->write("Downloading manual v{$latestVersion}...");

        try {
            $downloader = $this->getDownloader();
            $downloader->setTempDir(\sys_get_temp_dir());
            $downloaded = $downloader->download($downloadUrl);
        } catch (ErrorException $e) {
            $output->write(' <error>Failed.</error>');
            $output->writeln(\sprintf('<error>%s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        if (!$downloaded) {
            $output->writeln(' <error>Download failed.</error>');
            $downloader->cleanup();

            return self::FAILURE;
        }

        $output->write(' <info>OK</info>'.\PHP_EOL);

        $downloadedFile = $downloader->getFilename();

        if (!$this->installer->install($downloadedFile)) {
            $downloader->cleanup();
            $output->writeln('<error>Failed to install manual.</error>');

            return self::FAILURE;
        }

        // Clean up downloaded file
        $downloader->cleanup();

        $installPath = ConfigPaths::prettyPath($this->installer->getInstallPath());
        $output->writeln("Installed manual v{$latestVersion} to <info>{$installPath}</info>");

        return self::SUCCESS;
    }
}
