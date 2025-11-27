<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\ManualUpdater;

use Psy\ManualUpdater\Checker;
use Psy\ManualUpdater\IntervalChecker;
use Psy\Test\TestCase;

class IntervalCheckerTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = \tempnam(\sys_get_temp_dir(), 'psysh_manual_interval_test_');
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->cacheFile)) {
            @\unlink($this->cacheFile);
        }
    }

    public function testIsLatestWithNoCacheFetchesFromChecker()
    {
        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('isLatest')
            ->willReturn(true);

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertTrue($checker->isLatest());
    }

    public function testIsLatestUsesCacheWhenValid()
    {
        // Pre-populate cache with recent check
        $cacheData = [
            'last_check' => \date(\DATE_ATOM),
            'is_latest'  => false,
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->never())->method('isLatest');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertFalse($checker->isLatest());
    }

    public function testIsLatestRefetchesWhenCacheExpired()
    {
        // Pre-populate cache with old check
        $oldDate = (new \DateTime())->sub(new \DateInterval('P2D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $oldDate,
            'is_latest'  => false,
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('isLatest')
            ->willReturn(true);

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertTrue($checker->isLatest());
    }

    public function testGetLatestWithNoCache()
    {
        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('getLatest')
            ->willReturn('2.0.0');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertSame('2.0.0', $checker->getLatest());
    }

    public function testGetLatestUsesCacheWhenValid()
    {
        $cacheData = [
            'last_check'     => \date(\DATE_ATOM),
            'latest_version' => '1.5.0',
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->never())->method('getLatest');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertSame('1.5.0', $checker->getLatest());
    }

    public function testGetDownloadUrlAlwaysDelegates()
    {
        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('getDownloadUrl')
            ->willReturn('https://example.com/download');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertSame('https://example.com/download', $checker->getDownloadUrl());
    }

    public function testWeeklyInterval()
    {
        // Pre-populate cache with 3-day-old check (should still be valid for weekly)
        $date = (new \DateTime())->sub(new \DateInterval('P3D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $date,
            'is_latest'  => true,
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->never())->method('isLatest');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::WEEKLY);

        $this->assertTrue($checker->isLatest());
    }

    public function testMonthlyInterval()
    {
        // Pre-populate cache with 2-week-old check (should still be valid for monthly)
        $date = (new \DateTime())->sub(new \DateInterval('P14D'))->format(\DATE_ATOM);
        $cacheData = [
            'last_check' => $date,
            'is_latest'  => true,
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->never())->method('isLatest');

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::MONTHLY);

        $this->assertTrue($checker->isLatest());
    }

    public function testInvalidIntervalTreatsAsCacheInvalid()
    {
        // With invalid interval, getDateInterval() throws but it's caught,
        // so cache is treated as invalid and checker is called
        $cacheData = [
            'last_check' => \date(\DATE_ATOM),
            'is_latest'  => true,
        ];
        \file_put_contents($this->cacheFile, \json_encode($cacheData));

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('isLatest')
            ->willReturn(false);

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, 'invalid');

        // Should call the inner checker since cache validation fails
        $this->assertFalse($checker->isLatest());
    }

    public function testCorruptedCacheIsHandled()
    {
        \file_put_contents($this->cacheFile, 'not valid json');

        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->expects($this->once())
            ->method('isLatest')
            ->willReturn(true);

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);

        $this->assertTrue($checker->isLatest());
    }

    public function testCacheIsWrittenAfterFetch()
    {
        $innerChecker = $this->createMock(Checker::class);
        $innerChecker->method('isLatest')->willReturn(true);

        $checker = new IntervalChecker($innerChecker, $this->cacheFile, Checker::DAILY);
        $checker->isLatest();

        $this->assertFileExists($this->cacheFile);

        $cached = \json_decode(\file_get_contents($this->cacheFile), true);
        $this->assertArrayHasKey('last_check', $cached);
        $this->assertArrayHasKey('is_latest', $cached);
        $this->assertTrue($cached['is_latest']);
    }
}
