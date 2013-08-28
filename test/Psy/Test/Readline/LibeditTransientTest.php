<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline;

use Psy\Readline\LibeditTransient;

class LibeditTransientTest extends \PHPUnit_Framework_TestCase
{
    private $readline;

    public function setUp()
    {
        if (!function_exists('readline')) {
            $this->markTestSkipped('Libedit not enabled');
        }

        $this->readline = new LibeditTransient();
    }

    public function testHistory()
    {
        $this->assertEmpty($this->readline->listHistory());
        $this->readline->addHistory('foo');
        $this->assertEquals(array('foo'), $this->readline->listHistory());
        $this->readline->addHistory('bar');
        $this->assertEquals(array('foo', 'bar'), $this->readline->listHistory());
        $this->readline->addHistory('baz');
        $this->assertEquals(array('foo', 'bar', 'baz'), $this->readline->listHistory());
        $this->readline->clearHistory();
        $this->assertEmpty($this->readline->listHistory());
    }
}
