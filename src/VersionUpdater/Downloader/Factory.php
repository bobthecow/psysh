<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VersionUpdater\Downloader;

use Psy\Exception\ErrorException;
use Psy\Util\DependencyChecker;
use Psy\VersionUpdater\Downloader;

class Factory
{
    private const CURL_FUNCTIONS = [
        'curl_init',
        'curl_setopt_array',
        'curl_setopt',
        'curl_exec',
        'curl_error',
        'curl_close',
    ];

    /**
     * @throws ErrorException If no downloaders can be used
     */
    public static function getDownloader(): Downloader
    {
        if (DependencyChecker::functionsAvailable(self::CURL_FUNCTIONS)) {
            return new CurlDownloader();
        } elseif (\ini_get('allow_url_fopen')) {
            return new FileDownloader();
        }
        throw new ErrorException('No downloader available.');
    }
}
