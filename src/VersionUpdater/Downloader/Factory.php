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
use Psy\Exception\ErrorException;

class Factory
{
    /**
     * @return Downloader
     * @throws ErrorException If no downloaders can be used
     */
    public static function GetDownloader() : Downloader
    {
        if (extension_loaded("curl")) {
            return new CurlDownloader();
        } else if (\ini_get('allow_url_fopen')) {
            return new FileDownloader();
        }
        throw new ErrorException("No downloader available.");
    }
}
