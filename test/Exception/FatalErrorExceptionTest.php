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

use Psy\Exception\Exception;
use Psy\Exception\FatalErrorException;

class FatalErrorExceptionTest extends \Psy\Test\TestCase
{
    public function testInstance()
    {
        $e = new FatalErrorException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(\ErrorException::class, $e);
        $this->assertInstanceOf(FatalErrorException::class, $e);
    }

    public function testMessage()
    {
        $e = new FatalErrorException('{msg}', 0, 0, '{filename}', 13);

        $this->assertSame('{msg}', $e->getRawMessage());
        $this->assertContains('{msg}', $e->getMessage());
        $this->assertContains('{filename}', $e->getMessage());
        $this->assertContains('line 13', $e->getMessage());
    }

    public function testMessageWithNoFilename()
    {
        $e = new FatalErrorException('{msg}');

        $this->assertSame('{msg}', $e->getRawMessage());
        $this->assertContains('{msg}', $e->getMessage());
        $this->assertContains('eval()\'d code', $e->getMessage());
    }

    public function testNegativeOneLineNumberIgnored()
    {
        if (\defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM does not support the line number argument, apparently.');
        }

        $e = new FatalErrorException('{msg}', 0, 1, null, -1);
        $this->assertEquals(0, $e->getLine());
    }
}
