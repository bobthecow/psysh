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

use Psy\Formatter\RecursiveFormatter;

class RecursiveFormatterTest extends \PHPUnit_Framework_TestCase
{
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
