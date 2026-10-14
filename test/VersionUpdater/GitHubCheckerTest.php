<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Shell;
use Psy\Test\Fixtures\HttpsStream;
use Psy\Test\TestCase;
use Psy\VersionUpdater\GitHubChecker;

class GitHubCheckerTest extends TestCase
{
    /**
     * @dataProvider failedRequests
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFailedRequestPreservesReasonAndRestoresErrorHandler(?string $warning, string $message)
    {
        HttpsStream::register([], $warning === null ? [] : [GitHubChecker::URL => $warning]);
        $handler = function () {
            $this->fail('Network warnings should not reach the active error handler');
        };
        \set_error_handler($handler);

        try {
            try {
                (new GitHubChecker())->getLatest();
                $this->fail('Expected the failed request to throw');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage());
                $this->assertInstanceOf(\ErrorException::class, $e->getPrevious());
                if ($warning === null) {
                    $this->assertStringContainsStringIgnoringCase('Failed to open stream', $e->getPrevious()->getMessage());
                } else {
                    $this->assertSame($warning, $e->getPrevious()->getMessage());
                }
            }

            $restoredHandler = \set_error_handler($handler);
            \restore_error_handler();
            $this->assertSame($handler, $restoredHandler);
        } finally {
            \restore_error_handler();
            HttpsStream::restore();
        }
    }

    public function failedRequests(): array
    {
        $generic = 'Unable to check for updates. Please try again later.';
        $rateLimit = 'Unable to check for updates: GitHub\'s rate limit was exceeded. Please try again later.';

        return [
            'stream failure'    => [null, $generic],
            'rate limit'        => ['HTTP request failed! HTTP/1.1 403 rate limit exceeded', $rateLimit],
            'too many requests' => ['HTTP request failed! HTTP/1.1 429 Too Many Requests', $rateLimit],
            'forbidden'         => ['HTTP request failed! HTTP/1.1 403 Forbidden', $generic],
            'unknown error'     => ['SSL error: <info>private diagnostic</info>', $generic],
        ];
    }

    /**
     * @dataProvider malformedResults
     *
     * @param mixed $input
     */
    public function testExceptionInvocation($input)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to check for updates. Please try again later.');

        $checker = $this->getMockBuilder(GitHubChecker::class)
            ->setMethods(['fetchLatestRelease'])
            ->getMock();
        $checker->expects($this->once())->method('fetchLatestRelease')->willReturn($input);
        $checker->isLatest();

        $this->fail();
    }

    /**
     * @dataProvider jsonResults
     *
     * @group isolation-fail
     *
     * @param bool  $assertion
     * @param mixed $input
     */
    public function testDataSetResults($assertion, $input)
    {
        $checker = $this->getMockBuilder(GitHubChecker::class)
            ->setMethods(['fetchLatestRelease'])
            ->getMock();
        $checker->expects($this->once())->method('fetchLatestRelease')->willReturn($input);
        $this->assertSame($assertion, $checker->isLatest());
    }

    /**
     * @return array
     */
    public function jsonResults()
    {
        return [
            [false, \json_decode('{"tag_name":"v9.0.0"}')],
            [true, \json_decode('{"tag_name":"v'.Shell::VERSION.'"}')],
            [true, \json_decode('{"tag_name":"v0.0.1"}')],
            [true, \json_decode('{"tag_name":"v0.4.1-alpha"}')],
            [true, \json_decode('{"tag_name":"v0.4.2-beta3"}')],
            [true, \json_decode('{"tag_name":"v0.0.1"}')],
            [true, \json_decode('{"tag_name":""}')],
        ];
    }

    /**
     * @return array
     */
    public function malformedResults()
    {
        return [
            [null],
            [false],
            [true],
            [\json_decode('{"foo":"bar"}')],
            [\json_decode('{}')],
            [\json_decode('[]')],
            [[]],
            [\json_decode('{"tag_name":false"}')],
            [\json_decode('{"tag_name":true"}')],
        ];
    }
}
