<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\Exception;
use Psy\Exception\RuntimeException;

class RuntimeExceptionTest extends \Psy\Test\TestCase
{
    public function testException()
    {
        $msg = 'bananas';
        $e = new RuntimeException($msg);

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);

        $this->assertSame($msg, $e->getMessage());
        $this->assertSame($msg, $e->getRawMessage());
    }
}
