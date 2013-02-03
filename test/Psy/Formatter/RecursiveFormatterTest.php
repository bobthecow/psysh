<?php

/*
 * This file is part of PsySH
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Formatter;

use Psy\Formatter\RecursiveFormatter;

class RecursiveFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormattingFallsBackToJson()
    {
        $stub = new RecursiveFormatterStub;
        $this->assertEquals('1',    $stub->formatValue(1));
        $this->assertEquals('"1"',  $stub->formatValue("1"));
        $this->assertEquals('null', $stub->formatValue(null));
    }

    public function testResourcesAreFormatted()
    {
        $handle = tmpfile();
        $stub = new RecursiveFormatterStub;
        $this->assertStringMatchesFormat('<Resource id #%d>', $stub->formatValue($handle));
        fclose($handle);
    }

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testUnimplementedFormatThrowsException()
    {
        $stub = new RecursiveFormatterStub;
        $stub->format('nothing');
    }

    /**
     * @expectedException \Psy\Exception\FatalErrorException
     */
    public function testUnimplementedFormatRefThrowsException()
    {
        $stub = new RecursiveFormatterStub;
        $stub->formatRef('nothing');
    }
}

class RecursiveFormatterStub extends RecursiveFormatter
{

}
