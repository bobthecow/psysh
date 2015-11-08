<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\BreakException;
use Psy\Exception\Exception;

class BreakExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testInstance()
    {
        $e = new BreakException();

        $this->assertTrue($e instanceof Exception);
        $this->assertTrue($e instanceof BreakException);
    }

    public function testMessage()
    {
        $e = new BreakException('foo');

        $this->assertContains('foo', $e->getMessage());
        $this->assertEquals('foo', $e->getRawMessage());
    }
}
