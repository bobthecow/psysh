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

use Psy\CodeAnalysis\BufferAnalyzer;
use Psy\Readline\Interactive\Input\StatementCompletenessPolicy;
use Psy\Test\TestCase;

class StatementCompletenessPolicyTest extends TestCase
{
    public function testTreatsSimpleStatementAsComplete(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertTrue($policy->isCompleteStatement('$value = 1'));
    }

    public function testRespectsRequireSemicolonsFlag(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), true);

        $this->assertFalse($policy->isCompleteStatement('$value = 1'));
        $this->assertTrue($policy->isCompleteStatement('$value = 1;'));
    }

    public function testDetectsUnclosedStringAsIncomplete(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->isCompleteStatement('echo "hello'));
    }

    public function testDetectsControlStructureWithoutBodyAsIncomplete(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->isCompleteStatement('if (true)'));
    }

    public function testAllowsImmediateExecutionForRealSyntaxErrors(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertTrue($policy->isCompleteStatement('class {'));
    }

    public function testDetectsUnrecoverableExtraClosingParen(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertTrue($policy->hasUnrecoverableSyntaxError('var_dump(1))'));
    }

    public function testDetectsUnrecoverableMissingClassName(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertTrue($policy->hasUnrecoverableSyntaxError('class {'));
    }

    public function testNoUnrecoverableErrorForValidCode(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->hasUnrecoverableSyntaxError('echo "hello"'));
    }

    public function testNoUnrecoverableErrorForIncompleteCode(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->hasUnrecoverableSyntaxError('$x = [1, 2'));
    }

    public function testNoUnrecoverableErrorForEmptyInput(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->hasUnrecoverableSyntaxError(''));
    }

    public function testNoUnrecoverableErrorForUnterminatedString(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->hasUnrecoverableSyntaxError('echo "hello'));
    }

    public function testNoUnrecoverableErrorForControlStructureWithoutBody(): void
    {
        $policy = new StatementCompletenessPolicy(new BufferAnalyzer(), false);

        $this->assertFalse($policy->hasUnrecoverableSyntaxError('if ($x)'));
    }
}
