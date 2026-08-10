<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use PhpParser\Error as PhpParserError;
use PhpParser\Parser;
use Psy\Command\CodeArgumentParser;
use Psy\Exception\ParseErrorException;
use Psy\Test\TestCase;

class CodeArgumentParserTest extends TestCase
{
    public function testRetriesPhpParserFiveUnexpectedEofWithSemicolon()
    {
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->exactly(2))
            ->method('parse')
            ->withConsecutive(['<?php echo 42'], ['<?php echo 42;'])
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new PhpParserError('Unexpected token EOF')),
                []
            );

        $this->assertSame([], (new CodeArgumentParser($parser))->parse('echo 42'));
    }

    public function testDoesNotRetryNonEofParseErrors()
    {
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())
            ->method('parse')
            ->willThrowException(new PhpParserError('Unexpected token ";"'));

        $this->expectException(ParseErrorException::class);

        (new CodeArgumentParser($parser))->parse('echo ;;');
    }
}
