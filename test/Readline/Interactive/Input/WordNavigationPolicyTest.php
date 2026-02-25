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

use Psy\Readline\Interactive\Input\WordNavigationPolicy;
use Psy\Test\TestCase;

class WordNavigationPolicyTest extends TestCase
{
    private WordNavigationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new WordNavigationPolicy();
    }

    public function testFindPreviousWordAtEndOfText(): void
    {
        $pos = $this->policy->findPreviousWord('hello world foo', 15);

        $this->assertSame(12, $pos);
    }

    public function testFindPreviousWordWithTrailingSpaces(): void
    {
        $pos = $this->policy->findPreviousWord('hello world   ', 14);

        $this->assertSame(6, $pos);
    }

    public function testFindPreviousWordAtStart(): void
    {
        $pos = $this->policy->findPreviousWord('hello world', 0);

        $this->assertSame(0, $pos);
    }

    public function testFindPreviousWordWithSingleWord(): void
    {
        $pos = $this->policy->findPreviousWord('hello', 5);

        $this->assertSame(0, $pos);
    }

    public function testFindPreviousWordMidWord(): void
    {
        $pos = $this->policy->findPreviousWord('hello world', 8);

        $this->assertSame(6, $pos);
    }

    public function testFindNextWordFromStart(): void
    {
        $pos = $this->policy->findNextWord('hello world foo', 0);

        $this->assertSame(5, $pos);
    }

    public function testFindNextWordWithExtraSpaces(): void
    {
        $pos = $this->policy->findNextWord('hello   world foo', 5);

        $this->assertSame(13, $pos);
    }

    public function testFindNextWordAtEnd(): void
    {
        $pos = $this->policy->findNextWord('hello', 5);

        $this->assertSame(5, $pos);
    }

    public function testFindNextWordWithSingleWord(): void
    {
        $pos = $this->policy->findNextWord('hello', 0);

        $this->assertSame(5, $pos);
    }

    public function testFindNextWordMidWord(): void
    {
        $pos = $this->policy->findNextWord('hello world', 2);

        $this->assertSame(5, $pos);
    }

    public function testFindPreviousWordEmptyString(): void
    {
        $pos = $this->policy->findPreviousWord('', 0);

        $this->assertSame(0, $pos);
    }

    public function testFindNextWordEmptyString(): void
    {
        $pos = $this->policy->findNextWord('', 0);

        $this->assertSame(0, $pos);
    }

    public function testFindPreviousWordWithSpecialChars(): void
    {
        $pos = $this->policy->findPreviousWord('$foo->bar', 9);

        $this->assertSame(6, $pos);
    }

    public function testFindNextWordWithSpecialChars(): void
    {
        $pos = $this->policy->findNextWord('$foo->bar', 4);

        $this->assertSame(9, $pos);
    }
}
