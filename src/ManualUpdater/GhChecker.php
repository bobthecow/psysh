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

/**
 * Manual checker using GitHub CLI (gh) with local credentials.
 *
 * This allows testing with private repositories before they're made public.
 * Requires the `gh` CLI tool to be installed and authenticated.
 */
class GhChecker implements Checker
{
    const REPO = 'bobthecow/psysh-manual';

    private string $lang;
    private string $format;
    private ?string $currentVersion;
    private ?string $currentLang;
    private ?string $latestVersion = null;
    private ?string $downloadUrl = null;

    /**
     * @param string      $lang           Language code (e.g., 'en')
     * @param string      $format         Format type ('php' or 'sqlite')
     * @param string|null $currentVersion Current manual version, or null if not installed
     * @param string|null $currentLang    Current manual language, or null if not installed
     */
    public function __construct(string $lang, string $format, ?string $currentVersion = null, ?string $currentLang = null)
    {
        $this->lang = $lang;
        $this->format = $format;
        $this->currentVersion = $currentVersion;
        $this->currentLang = $currentLang;
    }

    public function isLatest(): bool
    {
        if ($this->currentVersion === null) {
            return false;
        }

        // If language has changed, need to update regardless of version
        if ($this->currentLang !== null && $this->currentLang !== $this->lang) {
            return false;
        }

        return \version_compare($this->currentVersion, $this->getLatest(), '>=');
    }

    public function getLatest(): string
    {
        if (!isset($this->latestVersion)) {
            $this->fetchLatestRelease();
        }

        return $this->latestVersion;
    }

    public function getDownloadUrl(): string
    {
        if (!isset($this->downloadUrl)) {
            $this->fetchLatestRelease();
        }

        return $this->downloadUrl;
    }

    private function fetchLatestRelease()
    {
        // Check if gh CLI is available
        if (!\shell_exec('which gh 2>/dev/null')) {
            throw new \RuntimeException('gh CLI not found. Install with: brew install gh');
        }

        // Fetch releases using gh CLI
        $cmd = \sprintf('gh release list --repo %s --json tagName --limit 10 2>&1', \escapeshellarg(self::REPO));
        $output = \shell_exec($cmd);

        if (!$output) {
            throw new \RuntimeException('Unable to fetch releases using gh CLI');
        }

        $releases = \json_decode($output, true);
        if (!$releases || !\is_array($releases)) {
            throw new \RuntimeException('Invalid response from gh CLI: '.$output);
        }

        // Find the first release with a manifest
        foreach ($releases as $release) {
            $tagName = $release['tagName'];
            $manifest = $this->fetchManifest($tagName);
            if ($manifest === null) {
                continue;
            }

            // Find our language/format in the manifest
            foreach ($manifest['manuals'] as $manual) {
                if ($manual['lang'] === $this->lang && $manual['format'] === $this->format) {
                    $this->latestVersion = $manual['version'];

                    // Build download URL using gh CLI
                    $filename = \sprintf('psysh-manual-v%s-%s.tar.gz', $manual['version'], $this->lang);

                    // Verify the asset exists in the release
                    if ($this->assetExists($tagName, $filename)) {
                        $this->downloadUrl = $this->getAssetDownloadUrl($tagName, $filename);

                        return;
                    }

                    throw new \RuntimeException(\sprintf('Asset "%s" not found in release %s', $filename, $tagName));
                }
            }
        }

        throw new \RuntimeException(\sprintf('No manual found for language "%s" in format "%s"', $this->lang, $this->format));
    }

    /**
     * Check if an asset exists in a release.
     *
     * @param string $tagName  Release tag name
     * @param string $filename Asset filename
     *
     * @return bool
     */
    private function assetExists(string $tagName, string $filename): bool
    {
        $cmd = \sprintf(
            'gh release view %s --repo %s --json assets 2>&1',
            \escapeshellarg($tagName),
            \escapeshellarg(self::REPO)
        );

        $output = \shell_exec($cmd);
        if (!$output) {
            return false;
        }

        $data = \json_decode($output, true);
        if (!$data || !isset($data['assets'])) {
            return false;
        }

        foreach ($data['assets'] as $asset) {
            if ($asset['name'] === $filename) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch and parse manifest.json from a release.
     *
     * @param string $tagName Release tag name
     *
     * @return array|null
     */
    private function fetchManifest(string $tagName): ?array
    {
        $cmd = \sprintf(
            'gh release download %s --repo %s --pattern manifest.json --dir /tmp --clobber 2>&1',
            \escapeshellarg($tagName),
            \escapeshellarg(self::REPO)
        );

        \shell_exec($cmd);

        $manifestPath = '/tmp/manifest.json';
        if (\file_exists($manifestPath)) {
            $content = \file_get_contents($manifestPath);
            \unlink($manifestPath);

            return \json_decode($content, true);
        }

        return null;
    }

    /**
     * Get the download URL for a release asset using gh CLI.
     *
     * The gh CLI handles authentication, so we can download from private repos.
     *
     * @param string $tagName  Release tag name
     * @param string $filename Asset filename
     *
     * @return string Download command that will be used by the downloader
     */
    private function getAssetDownloadUrl(string $tagName, string $filename): string
    {
        // Return a special URL format that the GhDownloader will recognize
        return \sprintf('gh://%s/%s/%s', self::REPO, $tagName, $filename);
    }
}
