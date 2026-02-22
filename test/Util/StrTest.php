<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Util\Str;

class StrTest extends \Psy\Test\TestCase
{
    /**
     * @dataProvider unvisProvider
     */
    public function testUnvis($input, $expected)
    {
        $this->assertSame($expected, Str::unvis($input));
    }

    public function unvisProvider()
    {
        return \json_decode(\file_get_contents(__DIR__.'/../Fixtures/unvis_fixtures.json'));
    }
}
