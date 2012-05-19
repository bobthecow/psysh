<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\ArrayFormatter;

class ArrayFormatterTest extends \PHPUnit_Framework_TestCase
{
	public function testFormat()
	{
		$this->assertEquals('[]', ArrayFormatter::format(array()));
		$this->assertEquals('[1]', self::strip(ArrayFormatter::format(array(1))));
		$this->assertEquals('[2,"string"]', self::strip(ArrayFormatter::format(array(2, "string"))));
		$this->assertEquals('["a"=>1,"b"=>2]', self::strip(ArrayFormatter::format(array('a' => 1, 'b' => 2))));
	}

	public function testFormatRef()
	{
		$this->assertEquals('Array(0)', ArrayFormatter::formatRef(array()));
		$this->assertEquals('Array(1)', ArrayFormatter::formatRef(array(1)));
		$this->assertEquals('Array(2)', ArrayFormatter::formatRef(array(1, 2)));
		$this->assertEquals('Array(3)', ArrayFormatter::formatRef(array(1, 2, 3)));
	}

	private static function strip($text)
	{
		return preg_replace('/\\s/', '', $text);
	}
}
