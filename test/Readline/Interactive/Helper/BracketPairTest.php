<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Helper;

use Psy\Readline\Interactive\Helper\BracketPair;
use Psy\Readline\Interactive\Input\Buffer;
use Psy\Test\Readline\Interactive\BufferAssertionTrait;
use Psy\Test\TestCase;

class BracketPairTest extends TestCase
{
    use BufferAssertionTrait;

    private Buffer $buffer;

    protected function setUp(): void
    {
        $this->buffer = new Buffer();
    }

    public function testShouldAutoCloseOpenParen()
    {
        $this->setBufferState($this->buffer, 'test<cursor>');

        $result = BracketPair::shouldAutoClose('(', $this->buffer);

        $this->assertTrue($result);
    }

    public function testShouldNotAutoCloseBeforeAlphanumeric()
    {
        $this->setBufferState($this->buffer, 'ar<cursor>ray');

        $result = BracketPair::shouldAutoClose('(', $this->buffer);

        $this->assertFalse($result);
    }

    public function testShouldNotAutoCloseBeforeDecomposedAlphanumeric()
    {
        $this->setBufferState($this->buffer, "te<cursor>\u{0065}\u{0301}st");

        $result = BracketPair::shouldAutoClose('(', $this->buffer);

        $this->assertFalse($result);
    }

    public function testShouldNotAutoCloseQuoteInsideString()
    {
        $this->setBufferState($this->buffer, '"hello <cursor>');

        $result = BracketPair::shouldAutoClose('"', $this->buffer);

        $this->assertFalse($result);
    }

    public function testShouldAutoCloseQuoteOutsideString()
    {
        $this->setBufferState($this->buffer, 'echo <cursor>');

        $result = BracketPair::shouldAutoClose('"', $this->buffer);

        $this->assertTrue($result);
    }

    public function testShouldSkipOverMatchingBracket()
    {
        $this->setBufferState($this->buffer, 'test(<cursor>)');

        $result = BracketPair::shouldSkipOver(')', $this->buffer);

        $this->assertTrue($result);
    }

    public function testShouldNotSkipOverNonMatchingBracket()
    {
        $this->setBufferState($this->buffer, 'test<cursor>');

        $result = BracketPair::shouldSkipOver(')', $this->buffer);

        $this->assertFalse($result);
    }

    public function testShouldDeletePair()
    {
        $this->setBufferState($this->buffer, 'test(<cursor>)');

        $result = BracketPair::shouldDeletePair($this->buffer);

        $this->assertTrue($result);
    }

    public function testShouldNotDeletePairWhenNotBetweenBrackets()
    {
        $this->setBufferState($this->buffer, 'test)<cursor>');

        $result = BracketPair::shouldDeletePair($this->buffer);

        $this->assertFalse($result);
    }

    public function testGetClosingBracket()
    {
        $this->assertEquals(')', BracketPair::getClosingBracket('('));
        $this->assertEquals(']', BracketPair::getClosingBracket('['));
        $this->assertEquals('}', BracketPair::getClosingBracket('{'));
        $this->assertEquals('"', BracketPair::getClosingBracket('"'));
        $this->assertEquals("'", BracketPair::getClosingBracket("'"));
    }

    public function testGetClosingBracketInvalid()
    {
        $this->assertNull(BracketPair::getClosingBracket('x'));
    }

    public function testCountUnescapedQuotes()
    {
        $this->setBufferState($this->buffer, 'echo "hello \\"world\\" <cursor>');

        // Should detect we're inside string (odd number of unescaped quotes)
        $result = BracketPair::shouldAutoClose('"', $this->buffer);

        $this->assertFalse($result);
    }

    public function testAllBracketTypes()
    {
        foreach (['(', '[', '{'] as $bracket) {
            $this->setBufferState($this->buffer, 'test<cursor>');

            $result = BracketPair::shouldAutoClose($bracket, $this->buffer);

            $this->assertTrue($result, "Failed for bracket: $bracket");
        }
    }

    public function testClosingBracketMatchesOpeningBrace()
    {
        $this->assertTrue(BracketPair::doesClosingBracketMatch('}', 'if ($x) {'));
    }

    public function testClosingBracketMatchesOpeningBracket()
    {
        $this->assertTrue(BracketPair::doesClosingBracketMatch(']', '$arr = ['));
    }

    public function testClosingBracketMatchesOpeningParen()
    {
        $this->assertTrue(BracketPair::doesClosingBracketMatch(')', 'foo('));
    }

    public function testClosingBracketDoesNotMatchMismatchedOpener()
    {
        $this->assertFalse(BracketPair::doesClosingBracketMatch('}', '$arr = ['));
    }

    public function testClosingBracketDoesNotMatchWhenNoOpener()
    {
        $this->assertFalse(BracketPair::doesClosingBracketMatch('}', '$x = 1'));
    }

    public function testClosingBracketMatchesInnermostNested()
    {
        $this->assertTrue(BracketPair::doesClosingBracketMatch(']', "if (\$x) {\n    \$arr = ["));
    }

    public function testClosingBracketDoesNotMatchOuterWhenInnerOpen()
    {
        $this->assertFalse(BracketPair::doesClosingBracketMatch('}', "if (\$x) {\n    \$arr = ["));
    }

    public function testClosingBracketIgnoresBracketsInStrings()
    {
        $this->assertTrue(BracketPair::doesClosingBracketMatch(')', 'foo("test[", '));
    }

    public function testClosingBracketReturnsFalseForInvalidChar()
    {
        $this->assertFalse(BracketPair::doesClosingBracketMatch('x', 'foo('));
    }

    public function testIsInsideEmptyParens()
    {
        $this->setBufferState($this->buffer, 'foo(<cursor>)');

        $this->assertTrue(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsInsideEmptyBrackets()
    {
        $this->setBufferState($this->buffer, '$arr[<cursor>]');

        $this->assertTrue(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsInsideEmptyBraces()
    {
        $this->setBufferState($this->buffer, 'function foo() {<cursor>}');

        $this->assertTrue(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsNotInsideEmptyBracketsWithContent()
    {
        $this->setBufferState($this->buffer, 'foo(<cursor>bar)');

        $this->assertFalse(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsNotInsideEmptyBracketsAtStart()
    {
        $this->setBufferState($this->buffer, '<cursor>foo()');

        $this->assertFalse(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsNotInsideEmptyBracketsAtEnd()
    {
        $this->setBufferState($this->buffer, 'foo()<cursor>');

        $this->assertFalse(BracketPair::isInsideEmptyBrackets($this->buffer));
    }

    public function testIsNotInsideEmptyQuotes()
    {
        $this->setBufferState($this->buffer, '"<cursor>"');

        // isInsideEmptyBrackets excludes quotes; only structural brackets
        $this->assertFalse(BracketPair::isInsideEmptyBrackets($this->buffer));
    }
}
