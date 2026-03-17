<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Refiner;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Refiner\PartialInputRefiner;
use Psy\Test\TestCase;

class PartialInputRefinerTest extends TestCase
{
    public function testDoesNotPromoteParsedNumericLiteralToSymbol(): void
    {
        $refiner = new PartialInputRefiner();
        $analysis = new AnalysisResult(CompletionKind::UNKNOWN, '', null, [], null, [], '1', null, [], true);

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::UNKNOWN, $result->kinds);
        $this->assertSame('', $result->prefix);
    }

    public function testPromotesUnparsedIdentifierTailToSymbol(): void
    {
        $refiner = new PartialInputRefiner();
        $analysis = new AnalysisResult(CompletionKind::UNKNOWN, '', null, [], null, [], 'config s');

        $result = $refiner->refine($analysis);

        $this->assertSame(CompletionKind::SYMBOL, $result->kinds);
        $this->assertSame('s', $result->prefix);
    }
}
