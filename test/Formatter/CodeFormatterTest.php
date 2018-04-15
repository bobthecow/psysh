<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2018 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\CodeFormatter;

class CodeFormatterTest extends \PHPUnit\Framework\TestCase
{
    private function ignoreThisMethod($arg)
    {
        echo 'whot!';
    }

    public function testFormat()
    {
        $expected = <<<'EOS'
  > 18|     private function ignoreThisMethod($arg)
    19|     {
    20|         echo 'whot!';
    21|     }
EOS;

        $formatted = CodeFormatter::format(new \ReflectionMethod($this, 'ignoreThisMethod'));
        $formattedWithoutColors = preg_replace('#' . chr(27) . '\[\d\d?m#', '', $formatted);

        $this->assertEquals($expected, rtrim($formattedWithoutColors));
        $this->assertNotEquals($expected, rtrim($formatted));
    }

    /**
     * @dataProvider filenames
     * @expectedException \Psy\Exception\RuntimeException
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
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('We have issues with PHPUnit mocks on HHVM.');
        }

        return [[null], ['not a file']];
    }
}
