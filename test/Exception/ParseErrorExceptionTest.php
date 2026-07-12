<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Exception;

use PhpParser\Error as PhpParserError;
use Psy\Exception\Exception;
use Psy\Exception\ParseErrorException;
use Psy\Test\TestCase;

class ParseErrorExceptionTest extends TestCase
{
    public function testInstance()
    {
        $e = new ParseErrorException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(PhpParserError::class, $e);
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
        $e = ParseErrorException::fromParseError(new PhpParserError('{msg}'));

        $this->assertStringContainsString('{msg}', $e->getRawMessage());
        $this->assertStringContainsString('PHP Parse error:', $e->getMessage());
    }

    /**
     * @dataProvider unexpectedEOFProvider
     */
    public function testisUnexpectedEOF(string $message, bool $expected)
    {
        $this->assertSame($expected, ParseErrorException::isUnexpectedEOF(new PhpParserError($message)));
    }

    public static function unexpectedEOFProvider()
    {
        return [
            'php-parser v5' => ['Unexpected token EOF', true],
            'php-parser v4' => ['Syntax error, unexpected EOF on line 1', true],
            'other error'   => ['Unexpected token ";"', false],
        ];
    }
}
