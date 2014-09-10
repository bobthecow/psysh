<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Util;

use Psy\Util\Inspector;

class InspectorTest extends \PHPUnit_Framework_TestCase
{
    public $foo    = 'FOO';
    protected $bar = 'BAR';
    private $baz   = 'BAZ';
    private $qux   = array('a' => array('b' => array('c' => 'value')));

    public function testExport()
    {
        $result = Inspector::export($this, 3);

        $this->assertEquals('Psy\Test\Util\InspectorTest', $result->{'__CLASS__'});
        $this->assertEquals('FOO', $result->foo);
        $this->assertEquals('BAR', $result->bar);
        $this->assertEquals('BAZ', $result->baz);
        $this->assertEquals(array('a' => array('b' => 'Array(1)')), $result->qux);

        $result = Inspector::export(new \StdClass(), 0);
        $this->assertInternalType('string', $result);
        $this->assertRegExp('/<stdClass #[0-9a-f]+>/i', $result);

        $num = 13;
        $result = Inspector::export($num, 0);
        $this->assertInternalType('integer', $result);
        $this->assertEquals($num, $result);
    }
}
