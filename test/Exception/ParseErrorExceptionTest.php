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
use Psy\Exception\ParseErrorException;

/**
 * @group isolation-fail
 */
class ParseErrorExceptionTest extends \Psy\Test\TestCase
{
    public function testInstance()
    {
        $e = new ParseErrorException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(\PhpParser\Error::class, $e);
        $this->assertInstanceOf(ParseErrorException::class, $e);
    }

    public function testMessage()
    {
        $e = new ParseErrorException('{msg}', 1);

        $this->assertStringContainsString('{msg}', $e->getMessage());
        $this->assertStringContainsString('PHP Parse error:', $e->getMessage());
    }

    public function testConstructFromParseError()
    {
        $e = ParseErrorException::fromParseError(new \PhpParser\Error('{msg}'));

        $this->assertStringContainsString('{msg}', $e->getRawMessage());
        $this->assertStringContainsString('PHP Parse error:', $e->getMessage());
    }
}
