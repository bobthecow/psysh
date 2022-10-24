<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2022 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VersionUpdater\Downloader;

use Psy\VersionUpdater\Downloader;

class FileDownloader implements Downloader
{
    private $tempDir = null;
    private $outputFile = null;

    public function setTempDir(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    public function download(string $url): bool
    {
        $tempDir = $this->tempDir ?: \sys_get_temp_dir();
        $this->outputFile = \tempnam($tempDir, 'psysh-').'.tar.gz';

        return (bool) \file_put_contents($this->outputFile, \file_get_contents($url));
    }

    public function getFilename(): string
    {
        return $this->outputFile;
    }
}
