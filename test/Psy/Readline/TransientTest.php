<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline;

use Psy\Readline\Transient;

class TransientTest extends \PHPUnit_Framework_TestCase
{
    private $historyFile;
    private $readline;

    public function setUp()
    {
        $this->readline = new Transient;
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

    public function testSomeThingsAreAlwaysTrue()
    {
        $this->assertTrue(Transient::isSupported());
        $this->assertTrue($this->readline->readHistory());
        $this->assertTrue($this->readline->writeHistory());
    }

}
