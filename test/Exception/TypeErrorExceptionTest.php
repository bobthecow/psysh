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
use Psy\Exception\TypeErrorException;

class TypeErrorExceptionTest extends \Psy\Test\TestCase
{
    public function testException()
    {
        $e = new TypeErrorException('{{message}}', 13);

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(TypeErrorException::class, $e);

        $this->assertSame('TypeError: {{message}}', $e->getMessage());
        $this->assertSame('{{message}}', $e->getRawMessage());
        $this->assertSame(13, $e->getCode());
    }

    public function testStripsEvalFromMessage()
    {
        $message = 'Something or other, called in line 10: eval()\'d code';
        $e = new TypeErrorException($message);
        $this->assertSame($message, $e->getRawMessage());
        $this->assertSame('TypeError: Something or other', $e->getMessage());
    }

    public function testFromTypeError()
    {
        $previous = new \TypeError('{{message}}', 13);
        $e = TypeErrorException::fromTypeError($previous);

        $this->assertInstanceOf(TypeErrorException::class, $e);
        $this->assertSame('TypeError: {{message}}', $e->getMessage());
        $this->assertSame('{{message}}', $e->getRawMessage());
        $this->assertSame(13, $e->getCode());
    }
}
