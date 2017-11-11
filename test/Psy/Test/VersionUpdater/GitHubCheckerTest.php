<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\VersionUpdater;

use Psy\Shell;

class GitHubCheckerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider malformedResults
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unable to check for updates
     *
     * @param $input
     */
    public function testExceptionInvocation($input)
    {
        $checker = $this->getMockBuilder('Psy\\VersionUpdater\\GitHubChecker')
            ->setMethods(array('fetchLatestRelease'))
            ->getMock();
        $checker->expects($this->once())->method('fetchLatestRelease')->willReturn($input);
        $checker->isLatest();
    }

    /**
     * @dataProvider jsonResults
     *
     * @param $assertion
     * @param $input
     */
    public function testDataSetResults($assertion, $input)
    {
        $checker = $this->getMockBuilder('Psy\\VersionUpdater\\GitHubChecker')
            ->setMethods(array('fetchLatestRelease'))
            ->getMock();
        $checker->expects($this->once())->method('fetchLatestRelease')->willReturn($input);
        $this->assertSame($assertion, $checker->isLatest());
    }

    /**
     * @return array
     */
    public function jsonResults()
    {
        return array(
            array(false, json_decode('{"tag_name":"v9.0.0"}')),
            array(true, json_decode('{"tag_name":"v' . Shell::VERSION . '"}')),
            array(true, json_decode('{"tag_name":"v0.0.1"}')),
            array(true, json_decode('{"tag_name":"v0.4.1-alpha"}')),
            array(true, json_decode('{"tag_name":"v0.4.2-beta3"}')),
            array(true, json_decode('{"tag_name":"v0.0.1"}')),
            array(true, json_decode('{"tag_name":""}')),
        );
    }

    /**
     * @return array
     */
    public function malformedResults()
    {
        return array(
            array(null),
            array(false),
            array(true),
            array(json_decode('{"foo":"bar"}')),
            array(json_decode('{}')),
            array(json_decode('[]')),
            array(array()),
            array(json_decode('{"tag_name":false"}')),
            array(json_decode('{"tag_name":true"}')),
        );
    }
}
