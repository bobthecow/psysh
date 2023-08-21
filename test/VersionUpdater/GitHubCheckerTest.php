<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Shell;
use Psy\VersionUpdater\GitHubChecker;

class GitHubCheckerTest extends \Psy\Test\TestCase
{
    /**
     * @dataProvider malformedResults
     *
     * @param mixed $input
     */
    public function testExceptionInvocation($input)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to check for updates');

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
