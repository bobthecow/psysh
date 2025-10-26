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

use Psy\Exception\ErrorException;
use Psy\VersionUpdater\Downloader;

/**
 * Downloads release assets using GitHub CLI (gh).
 *
 * This allows downloading from private repositories using local credentials.
 */
class GhDownloader implements Downloader
{
    private string $tempDir;
    private ?string $filename = null;

    public function setTempDir(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    /**
     * Download a release asset using gh CLI.
     *
     * @param string $url Special format: gh://repo/tag/filename
     *
     * @throws ErrorException on failure
     */
    public function download(string $url): bool
    {
        // Parse the gh:// URL
        if (!\preg_match('#^gh://([^/]+/[^/]+)/([^/]+)/(.+)$#', $url, $matches)) {
            throw new ErrorException('Invalid gh:// URL format');
        }

        $repo = $matches[1];
        $tag = $matches[2];
        $filename = $matches[3];

        // Download using gh CLI
        $cmd = \sprintf(
            'gh release download %s --repo %s --pattern %s --dir %s --clobber 2>&1',
            \escapeshellarg($tag),
            \escapeshellarg($repo),
            \escapeshellarg($filename),
            \escapeshellarg($this->tempDir)
        );

        $output = \shell_exec($cmd);

        $this->filename = $this->tempDir.'/'.$filename;

        if (!\file_exists($this->filename)) {
            throw new ErrorException('Failed to download asset using gh CLI: '.$output);
        }

        return true;
    }

    public function getFilename(): string
    {
        if (!$this->filename) {
            throw new ErrorException('No file has been downloaded yet');
        }

        return $this->filename;
    }

    public function cleanup()
    {
        if ($this->filename && \file_exists($this->filename)) {
            \unlink($this->filename);
        }
    }
}
