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

use Psy\Exception\ErrorException;
use Psy\Exception\Exception;
use Psy\Exception\ThrowUpException;

class ThrowUpExceptionTest extends \Psy\Test\TestCase
{
    public function testException()
    {
        $previous = new \Exception('{{message}}', 123);
        $e = new ThrowUpException($previous);

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(ThrowUpException::class, $e);

        $this->assertEquals("Throwing Exception with message '{{message}}'", $e->getMessage());
        $this->assertEquals('{{message}}', $e->getRawMessage());
        $this->assertEquals(123, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testFromThrowable()
    {
        $previous = new \Exception('{{message}}');
        $e = ThrowUpException::fromThrowable($previous);

        $this->assertInstanceOf(ThrowUpException::class, $e);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testFromThrowableWithError()
    {
        if (\version_compare(\PHP_VERSION, '7.0.0', '<')) {
            $this->markTestSkipped();
        }

        $previous = new \Error('{{message}}');
        $e = ThrowUpException::fromThrowable($previous);

        $this->assertInstanceOf(ThrowUpException::class, $e);
        $this->assertInstanceOf(ErrorException::class, $e->getPrevious());

        $this->assertNotSame($previous, $e->getPrevious());
        $this->assertSame($previous, $e->getPrevious()->getPrevious());
    }

    public function testFromThrowableThrowsError()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('throw-up can only throw Exceptions and Errors');

        $notThrowable = new \stdClass();
        ThrowUpException::fromThrowable($notThrowable);

        $this->fail();
    }
}
