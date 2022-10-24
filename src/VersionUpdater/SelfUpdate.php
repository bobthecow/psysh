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
use Psy\Shell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Self update command.
 *
 * If a new version is available, this command will download it and replace the currently installed version
 */
class SelfUpdate
{
    const URL_PREFIX = 'https://github.com/bobthecow/psysh/releases/download';
    const SUCCESS = 0;
    const FAILURE = 1;

    /** @var Checker */
    private $checker;

    public function __construct(Checker $checker = null)
    {
        $this->checker = $checker;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $oldVersion = Shell::VERSION;
        $installLocation = \realpath($_SERVER['argv'][0]);
        $installDirectory = \dirname($installLocation);
        $installFile = \basename($installLocation);

        // already have the latest version?
        if ($this->checker->isLatest()) {
            // current version is latest version...
            $output->writeln('<info>Current version is up to date</info>');

            return self::SUCCESS;
        }
        // can overwrite current version?
        if (!\is_writable($installLocation)) {
            $output->writeln("<error>Installed version is not writable: $installLocation</error>");

            return self::FAILURE;
        }
        // can create backup next to current version?
        if (!\is_writable($installDirectory)) {
            $output->writeln('<error>Install direction is not writable.</error>');

            return self::FAILURE;
        }

        $latestVersion = $this->checker->getLatest();
        $downloadUrl = \sprintf('%s/%s/%s', self::URL_PREFIX, $latestVersion, "psysh-$latestVersion.tar.gz");

        $output->write("Downloading PsySH $latestVersion ...");

        try {
            $downloader = Downloader\Factory::getDownloader();
            $downloaded = $downloader->download($downloadUrl);
        } catch (ErrorException $e) {
            $output->write(' <error>Failed</error>');
            $output->writeln(\sprintf('<error>%s</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $downloadedFile = $downloader->getFilename();

        if (!$downloaded) {
            $output->writeln('<error>Download failed.</error>');
            if (\file_exists($downloadedFile)) {
                \unlink($downloadedFile);
            }
        } else {
            $output->write(" <info>OK</info>\n");
        }

        $pharArchive = new \PharData($downloadedFile);
        if (!$pharArchive->valid()) {
            \unlink($downloadedFile);
            $output->writeln('<error>Downloaded file is not a valid archive</error>');

            return self::FAILURE;
        }

        // backup: psysh -> psysh.old-version-number
        $backupFilename = \sprintf('%s/%s.%s', $installDirectory, $installFile, $oldVersion);

        // create backup as bin.old-version
        $backupCreated = \rename($installLocation, $backupFilename);
        if (!$backupCreated) {
            $output->writeln("<error>Failed to create backup: $backupFilename</error>");

            return self::FAILURE;
        }

        if ($pharArchive->extractTo(\dirname($installLocation), ['psysh'], true)) {
            \unlink($downloadedFile);
            if ($input->getOption('keep-backup')) {
                $output->writeln('Old version kept as: '.$backupFilename);
            } else {
                $output->writeln('Removing backup.');
                \unlink($backupFilename);
            }
            $output->writeln("Updated PsySH from $oldVersion to <info>$latestVersion</info>");
        } else {
            \rename($backupFilename, $installLocation);
            $output->writeln("<error>Failed to install new PsySH version $latestVersion</error>");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
