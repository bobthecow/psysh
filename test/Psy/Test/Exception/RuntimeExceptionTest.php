<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2017 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use Psy\Exception\Exception;
use Psy\Exception\RuntimeException;

class RuntimeExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testException()
    {
        $msg = 'bananas';
        $e   = new RuntimeException($msg);

        $this->assertTrue($e instanceof Exception);
        $this->assertTrue($e instanceof \RuntimeException);
        $this->assertTrue($e instanceof RuntimeException);

        $this->assertEquals($msg, $e->getMessage());
        $this->assertEquals($msg, $e->getRawMessage());
    }
}
