<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
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
        $this->assertStringContainsString('{msg}', $e->getMessage());
        $this->assertStringContainsString('{filename}', $e->getMessage());
        $this->assertStringContainsString('line 13', $e->getMessage());
    }

    public function testMessageWithNoFilename()
    {
        $e = new FatalErrorException('{msg}');

        $this->assertSame('{msg}', $e->getRawMessage());
        $this->assertStringContainsString('{msg}', $e->getMessage());
        $this->assertStringContainsString('eval()\'d code', $e->getMessage());
    }

    public function testNegativeOneLineNumberIgnored()
    {
        $e = new FatalErrorException('{msg}', 0, 1, null, -1);

        // In PHP 8.0+, the line number will be (as of the time of this change) 53, because it's
        // the line where the exception was first constructed. In older PHP versions, it'll be 0.
        $this->assertNotEquals(-1, $e->getLine());

        if (\version_compare(\PHP_VERSION, '8.0', '<')) {
            $this->assertSame(0, $e->getLine());
        }
    }
}
