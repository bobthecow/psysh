<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline;

use Psy\Readline\HoaConsole;

class HoaConsoleTest extends \PHPUnit_Framework_TestCase
{
    public function testHistory()
    {
        $readline = new HoaConsole();
        $this->assertEmpty($readline->listHistory());
        $readline->addHistory('foo');
        $this->assertEquals(array('foo'), $readline->listHistory());
        $readline->addHistory('bar');
        $this->assertEquals(array('foo', 'bar'), $readline->listHistory());
        $readline->addHistory('baz');
        $this->assertEquals(array('foo', 'bar', 'baz'), $readline->listHistory());
        $readline->clearHistory();
        $this->assertEmpty($readline->listHistory());
    }
}
