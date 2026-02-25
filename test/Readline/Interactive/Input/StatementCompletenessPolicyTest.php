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
use Psy\Readline\Interactive\Input\StatementCompletenessPolicy;
use Psy\Test\TestCase;

class StatementCompletenessPolicyTest extends TestCase
{
    public function testTreatsSimpleStatementAsComplete(): void
    {
        $policy = new StatementCompletenessPolicy(new ParseSnapshotCache(), false);

        $this->assertTrue($policy->isCompleteStatement('$value = 1'));
    }

    public function testRespectsRequireSemicolonsFlag(): void
    {
        $policy = new StatementCompletenessPolicy(new ParseSnapshotCache(), true);

        $this->assertFalse($policy->isCompleteStatement('$value = 1'));
        $this->assertTrue($policy->isCompleteStatement('$value = 1;'));
    }

    public function testDetectsUnclosedStringAsIncomplete(): void
    {
        $policy = new StatementCompletenessPolicy(new ParseSnapshotCache(), false);

        $this->assertFalse($policy->isCompleteStatement('echo "hello'));
    }

    public function testDetectsControlStructureWithoutBodyAsIncomplete(): void
    {
        $policy = new StatementCompletenessPolicy(new ParseSnapshotCache(), false);

        $this->assertFalse($policy->isCompleteStatement('if (true)'));
    }

    public function testAllowsImmediateExecutionForRealSyntaxErrors(): void
    {
        $policy = new StatementCompletenessPolicy(new ParseSnapshotCache(), false);

        $this->assertTrue($policy->isCompleteStatement('class {'));
    }
}
