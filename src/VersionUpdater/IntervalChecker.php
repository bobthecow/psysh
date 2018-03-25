<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\VersionUpdater;

use Psy\Shell;

class IntervalChecker extends GitHubChecker
{
    private $cacheFile;
    private $interval;

    public function __construct($cacheFile, $interval)
    {
        $this->cacheFile = $cacheFile;
        $this->interval  = $interval;
    }

    public function fetchLatestRelease()
    {
        // Read the cached file
        $cached = json_decode(@file_get_contents($this->cacheFile, false));
        if ($cached && isset($cached->last_check) && isset($cached->release)) {
            $now = new \DateTime();
            $lastCheck = new \DateTime($cached->last_check);
            if ($lastCheck >= $now->sub($this->getDateInterval())) {
                return $cached->release;
            }
        }

        // Fall back to fetching from GitHub
        $release = parent::fetchLatestRelease();
        if ($release && isset($release->tag_name)) {
            $this->updateCache($release);
        }

        return $release;
    }

    private function getDateInterval()
    {
        switch ($this->interval) {
            case Checker::DAILY:
                return new \DateInterval('P1D');
            case Checker::WEEKLY:
                return new \DateInterval('P1W');
            case Checker::MONTHLY:
                return new \DateInterval('P1M');
        }
    }

    private function updateCache($release)
    {
        $data = [
            'last_check' => date(DATE_ATOM),
            'release'    => $release,
        ];

        file_put_contents($this->cacheFile, json_encode($data));
    }
}
