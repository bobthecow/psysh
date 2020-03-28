<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\BreakException;
use Psy\Exception\Exception;

class BreakExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testInstance()
    {
        $e = new BreakException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(BreakException::class, $e);
    }

    public function testMessage()
    {
        $e = new BreakException('foo');

        $this->assertContains('foo', $e->getMessage());
        $this->assertSame('foo', $e->getRawMessage());
    }

    /**
     * @expectedException \Psy\Exception\BreakException
     * @expectedExceptionMessage Goodbye
     */
    public function testExitShell()
    {
        BreakException::exitShell();
    }
}
