<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Test\TestCase;
use Psy\VersionUpdater\Checker;
use Psy\VersionUpdater\IntervalChecker;

class IntervalCheckerTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = \tempnam(\sys_get_temp_dir(), 'psysh_version_interval_test_');
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->cacheFile)) {
            @\unlink($this->cacheFile);
        }
    }

    public function testFetchLatestReleaseUsesCacheWhenValid()
    {
        $cacheData = [
            'last_check' => \date(\DATE_ATOM),
            'release'    => ['tag_name' => 'v0.9.0'],
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $checker = new IntervalChecker($this->cacheFile, Checker::DAILY);
        $release = $checker->fetchLatestRelease();

        // Should return cached version
        $this->assertSame('v0.9.0', $release->tag_name);
    }

    public function testWeeklyIntervalUsesValidCache()
    {
        // 3-day-old cache should still be valid for weekly checks
        $date = (new \DateTime())->sub(new \DateInterval('P3D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $date,
            'release'    => ['tag_name' => 'v0.9.0'],
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $checker = new IntervalChecker($this->cacheFile, Checker::WEEKLY);
        $release = $checker->fetchLatestRelease();

        $this->assertSame('v0.9.0', $release->tag_name);
    }

    public function testMonthlyIntervalUsesValidCache()
    {
        // 2-week-old cache should still be valid for monthly checks
        $date = (new \DateTime())->sub(new \DateInterval('P14D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $date,
            'release'    => ['tag_name' => 'v0.9.0'],
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $checker = new IntervalChecker($this->cacheFile, Checker::MONTHLY);
        $release = $checker->fetchLatestRelease();

        $this->assertSame('v0.9.0', $release->tag_name);
    }

    public function testExpiredDailyCacheIsNotUsed()
    {
        $this->markTestSkipped('Test requires network call interception');

        // 2-day-old cache should be expired for daily checks
        $oldDate = (new \DateTime())->sub(new \DateInterval('P2D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $oldDate,
            'release'    => ['tag_name' => 'v0.9.0'],
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $checker = new IntervalChecker($this->cacheFile, Checker::DAILY);
        $release = $checker->fetchLatestRelease();

        // Cache is expired, so should fetch from network
        // Network call will return latest version (not v0.9.0)
        $this->assertNotNull($release);
        $this->assertNotSame('v0.9.0', $release->tag_name);
    }
}
