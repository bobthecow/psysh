<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Input;

use Psy\Readline\Interactive\Input\IndentationPolicy;
use Psy\Readline\Interactive\Input\ParseSnapshotCache;
use Psy\Test\TestCase;

class IndentationPolicyTest extends TestCase
{
    public function testAddsIndentAfterOpeningBrace(): void
    {
        $policy = new IndentationPolicy();
        $tokens = (new ParseSnapshotCache())->getSnapshot('if ($x) {')->getTokens();

        $this->assertSame('    ', $policy->calculateNextLineIndent('if ($x) {', $tokens));
    }

    public function testAddsIndentAfterControlStructure(): void
    {
        $policy = new IndentationPolicy();
        $tokens = (new ParseSnapshotCache())->getSnapshot('foreach ($items as $item)')->getTokens();

        $this->assertSame('    ', $policy->calculateNextLineIndent('foreach ($items as $item)', $tokens));
    }

    public function testMaintainsIndentOnTrailingOperator(): void
    {
        $policy = new IndentationPolicy();
        $line = '    $result = $a +';
        $tokens = (new ParseSnapshotCache())->getSnapshot($line)->getTokens();

        $this->assertSame('    ', $policy->calculateNextLineIndent($line, $tokens));
    }

    public function testSuppressesIndentInsideUnterminatedString(): void
    {
        $policy = new IndentationPolicy();
        $line = 'echo "hello';
        $tokens = (new ParseSnapshotCache())->getSnapshot($line)->getTokens();

        $this->assertSame('', $policy->calculateNextLineIndent($line, $tokens));
    }

    public function testPreservesIndentAfterSingleLineComment(): void
    {
        $policy = new IndentationPolicy();
        $line = '    $x = 1; // set x';
        $tokens = (new ParseSnapshotCache())->getSnapshot($line)->getTokens();

        $this->assertSame('    ', $policy->calculateNextLineIndent($line, $tokens));
    }

    public function testDedentWithMixedTabAndSpaces(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame("\t", $policy->dedent("\t    "));
    }

    public function testDedentWithTabOnlyIndentation(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame("\t", $policy->dedent("\t\t"));
    }

    // calculateClosingBracketDedent tests

    public function testDedentClosingBrace(): void
    {
        $policy = new IndentationPolicy();

        // Line is 4 spaces, cursor at end
        $this->assertSame(4, $policy->calculateClosingBracketDedent('}', '    ', 4));
    }

    public function testDedentClosingBracket(): void
    {
        $policy = new IndentationPolicy();

        // 8 spaces, removes one indent level (4)
        $this->assertSame(4, $policy->calculateClosingBracketDedent(']', '        ', 8));
    }

    public function testDedentClosingParen(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame(4, $policy->calculateClosingBracketDedent(')', '    ', 4));
    }

    public function testNoDedentForRegularCharacters(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame(0, $policy->calculateClosingBracketDedent('a', '    ', 4));
    }

    public function testNoDedentWhenLineHasContent(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame(0, $policy->calculateClosingBracketDedent('}', '    echo', 8));
    }

    public function testNoDedentWhenCursorNotAtEndOfWhitespace(): void
    {
        $policy = new IndentationPolicy();

        // Cursor in middle of whitespace
        $this->assertSame(0, $policy->calculateClosingBracketDedent('}', '    ', 2));
    }

    public function testNoDedentWhenNoLeadingSpaces(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame(0, $policy->calculateClosingBracketDedent('}', '', 0));
    }

    public function testPartialDedentWithFewerThanFourSpaces(): void
    {
        $policy = new IndentationPolicy();

        $this->assertSame(2, $policy->calculateClosingBracketDedent('}', '  ', 2));
    }

    public function testDedentWithMatchingBracketContext(): void
    {
        $policy = new IndentationPolicy();

        // Context has opening '[', closing ']' matches
        $this->assertSame(4, $policy->calculateClosingBracketDedent(']', '    ', 4, '$arr = ['));
    }

    public function testNoDedentWhenBracketsMismatch(): void
    {
        $policy = new IndentationPolicy();

        // Context has opening '[' but typing '}'
        $this->assertSame(0, $policy->calculateClosingBracketDedent('}', '    ', 4, '$arr = ['));
    }

    public function testDedentOnSecondLine(): void
    {
        $policy = new IndentationPolicy();

        // Multi-line buffer, cursor on second line which is just whitespace
        $text = "if (\$x) {\n    ";
        $cursor = \mb_strlen($text); // At end of second line

        $this->assertSame(4, $policy->calculateClosingBracketDedent('}', $text, $cursor, 'if ($x) {'));
    }
}
