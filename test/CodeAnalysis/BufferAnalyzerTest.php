<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeAnalysis;

use Psy\CodeAnalysis\BufferAnalyzer;
use Psy\Test\TestCase;

class BufferAnalyzerTest extends TestCase
{
    public function testParsesValidCodeAnalysis(): void
    {
        $service = new BufferAnalyzer();
        $analysis = $service->analyze('$value = 42;');

        $this->assertNotEmpty($analysis->getTokens());
        $this->assertNotEmpty($analysis->getTokenPositions());
        $this->assertNotNull($analysis->getAst());
        $this->assertNull($analysis->getLastError());
    }

    public function testCapturesParseErrorForInvalidCode(): void
    {
        $service = new BufferAnalyzer();
        $analysis = $service->analyze('if (true');

        $this->assertNotNull($analysis->getLastError());
        $this->assertNull($analysis->getAst());
    }

    public function testReusesCachedAnalysisForSameCode(): void
    {
        $service = new BufferAnalyzer();

        $first = $service->analyze('$a = 1;');
        $second = $service->analyze('$a = 1;');
        $this->assertSame($first, $second);
    }

    public function testReturnsDifferentAnalysisForDifferentCode(): void
    {
        $service = new BufferAnalyzer();

        $first = $service->analyze('$a = 1;');
        $second = $service->analyze('$b = 2;');
        $this->assertNotSame($first, $second);
    }

    public function testPartialTextDoesNotEvictFullBufferCache(): void
    {
        $service = new BufferAnalyzer();
        $fullCode = 'if ($x) { $y = 1; }';
        $partialCode = 'if ($x) { $y';

        $fullAnalysis = $service->analyze($fullCode);
        $partialAnalysis = $service->analyze($partialCode);

        $this->assertSame($fullAnalysis, $service->analyze($fullCode));
        $this->assertSame($partialAnalysis, $service->analyze($partialCode));
    }

    public function testSemicolonFixCheck(): void
    {
        $service = new BufferAnalyzer();

        $this->assertTrue($service->canBeFixedWithSemicolon('$a = 1'));
        $this->assertFalse($service->canBeFixedWithSemicolon('if ('));
    }

    public function testAnalysisExposesSharedBufferStateHelpers(): void
    {
        $service = new BufferAnalyzer();

        $this->assertTrue($service->analyze('return $foo +')->hasTrailingOperator());
        $this->assertFalse($service->analyze('[$foo')->hasBalancedBrackets());
        $this->assertTrue($service->analyze('echo "hello')->endsInOpenStringOrComment());
        $this->assertTrue($service->analyze('if ($foo)')->hasControlStructureWithoutBody());
        $this->assertTrue($service->analyze('} else')->hasControlStructureWithoutBody());
        $this->assertFalse($service->analyze('if ($foo) {')->hasControlStructureWithoutBody());
    }
}
