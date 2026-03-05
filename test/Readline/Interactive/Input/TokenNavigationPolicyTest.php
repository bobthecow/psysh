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

use Psy\Readline\Interactive\Input\ParseSnapshotCache;
use Psy\Readline\Interactive\Input\TokenNavigationPolicy;
use Psy\Test\TestCase;

class TokenNavigationPolicyTest extends TestCase
{
    private TokenNavigationPolicy $policy;
    private ParseSnapshotCache $parseSnapshotCache;

    protected function setUp(): void
    {
        $this->policy = new TokenNavigationPolicy();
        $this->parseSnapshotCache = new ParseSnapshotCache();
    }

    public function testFindPreviousTokenSkipsWhitespace(): void
    {
        $snapshot = $this->parseSnapshotCache->getSnapshot('$foo   ->   bar');

        $position = $this->policy->findPreviousToken(
            $snapshot->getTokens(),
            $snapshot->getTokenPositions(),
            12
        );

        $this->assertSame(7, $position);
    }

    public function testFindNextTokenSkipsWhitespaceAndMovesForward(): void
    {
        $snapshot = $this->parseSnapshotCache->getSnapshot('$foo   ->   bar');

        $position = $this->policy->findNextToken(
            $snapshot->getTokens(),
            $snapshot->getTokenPositions(),
            4,
            14
        );

        $this->assertSame(7, $position);
    }

    public function testFindNextTokenFallsBackToLineLength(): void
    {
        $snapshot = $this->parseSnapshotCache->getSnapshot('$foo');

        $position = $this->policy->findNextToken(
            $snapshot->getTokens(),
            $snapshot->getTokenPositions(),
            4,
            4
        );

        $this->assertSame(4, $position);
    }

    public function testFindPreviousTokenWithMultibyte(): void
    {
        $snapshot = $this->parseSnapshotCache->getSnapshot('é; $foo');

        // Cursor at end (7 code points: é, ;, space, $, f, o, o)
        $position = $this->policy->findPreviousToken(
            $snapshot->getTokens(),
            $snapshot->getTokenPositions(),
            7
        );

        // Should find start of $foo (at code-point position 3)
        $this->assertSame(3, $position);
    }

    public function testFindNextTokenWithMultibyte(): void
    {
        $snapshot = $this->parseSnapshotCache->getSnapshot('é; $foo');

        // Cursor at semicolon (position 1), find next non-whitespace token
        $position = $this->policy->findNextToken(
            $snapshot->getTokens(),
            $snapshot->getTokenPositions(),
            2,
            7
        );

        // Should find start of $foo (at code-point position 3)
        $this->assertSame(3, $position);
    }
}
