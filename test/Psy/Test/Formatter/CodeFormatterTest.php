<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\CodeFormatter;

class CodeFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormat()
    {
        $expected = <<<EOS
    private function ignoreThisMethod(\$arg)
    {
        echo "whot!";
    }
EOS;

        $this->assertEquals(
            str_replace("\n", PHP_EOL, $expected),
            CodeFormatter::format(new \ReflectionMethod($this, 'ignoreThisMethod'))
        );
    }

    /**
     * @dataProvider filenames
     * @expectedException Psy\Exception\RuntimeException
     */
    public function testCodeFormatterThrowsException($filename)
    {
        $reflector = $this->getMockBuilder('ReflectionClass')
            ->disableOriginalConstructor()
            ->getMock();

        $reflector
            ->expects($this->once())
            ->method('getFileName')
            ->will($this->returnValue($filename));

        CodeFormatter::format($reflector);
    }

    public function filenames()
    {
        return array(array(null), array('not a file'));
    }

    private function ignoreThisMethod($arg)
    {
        echo "whot!";
    }
}
