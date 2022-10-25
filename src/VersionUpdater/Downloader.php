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

interface Downloader
{
    /**
     * Set the directory where the download will be written to.
     *
     * @param string $tempDir
     */
    public function setTempDir(string $tempDir);

    /**
     * @param string $url
     *
     * @return bool
     *
     * @throws ErrorException on failure
     */
    public function download(string $url): bool;

    /**
     * Get the temporary file name the download was written to.
     *
     * @return string
     */
    public function getFilename(): string;

    /**
     * Delete the downloaded file if it exists.
     *
     * @return void
     */
    public function cleanup();
}
