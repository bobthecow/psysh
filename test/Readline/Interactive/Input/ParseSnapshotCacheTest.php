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
use Psy\Test\TestCase;

class ParseSnapshotCacheTest extends TestCase
{
    public function testParsesValidCodeSnapshot(): void
    {
        $service = new ParseSnapshotCache();
        $snapshot = $service->getSnapshot('$value = 42;');

        $this->assertNotEmpty($snapshot->getTokens());
        $this->assertNotEmpty($snapshot->getTokenPositions());
        $this->assertNotNull($snapshot->getAst());
        $this->assertNull($snapshot->getLastError());
    }

    public function testCapturesParseErrorForInvalidCode(): void
    {
        $service = new ParseSnapshotCache();
        $snapshot = $service->getSnapshot('if (true');

        $this->assertNotNull($snapshot->getLastError());
        $this->assertNull($snapshot->getAst());
    }

    public function testReusesCachedSnapshotForSameCode(): void
    {
        $service = new ParseSnapshotCache();

        $first = $service->getSnapshot('$a = 1;');
        $second = $service->getSnapshot('$a = 1;');
        $this->assertSame($first, $second);
    }

    public function testReturnsDifferentSnapshotForDifferentCode(): void
    {
        $service = new ParseSnapshotCache();

        $first = $service->getSnapshot('$a = 1;');
        $second = $service->getSnapshot('$b = 2;');
        $this->assertNotSame($first, $second);
    }

    /**
     * Requesting a snapshot for partial text (e.g. text before cursor) should
     * not evict the cached snapshot for the full buffer text.
     */
    public function testPartialTextDoesNotEvictFullBufferCache(): void
    {
        $service = new ParseSnapshotCache();
        $fullCode = 'if ($x) { $y = 1; }';
        $partialCode = 'if ($x) { $y';

        $fullSnapshot = $service->getSnapshot($fullCode);
        $partialSnapshot = $service->getSnapshot($partialCode);

        // Full buffer snapshot should still be cached
        $this->assertSame($fullSnapshot, $service->getSnapshot($fullCode));
        // Partial snapshot should also still be cached
        $this->assertSame($partialSnapshot, $service->getSnapshot($partialCode));
    }

    public function testSemicolonFixCheck(): void
    {
        $service = new ParseSnapshotCache();

        $this->assertTrue($service->canBeFixedWithSemicolon('$a = 1'));
        $this->assertFalse($service->canBeFixedWithSemicolon('if ('));
    }
}
