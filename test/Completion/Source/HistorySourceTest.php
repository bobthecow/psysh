<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion\Source;

use Psy\Completion\AnalysisResult;
use Psy\Completion\CompletionKind;
use Psy\Completion\Source\HistorySource;
use Psy\Readline\Interactive\Input\History;
use Psy\Test\TestCase;

class HistorySourceTest extends TestCase
{
    public function testAppliesToCommandContext()
    {
        $source = new HistorySource(new History());
        $this->assertTrue($source->appliesToKind(CompletionKind::COMMAND));
    }

    public function testDoesNotApplyToOtherContexts()
    {
        $source = new HistorySource(new History());
        $this->assertFalse($source->appliesToKind(CompletionKind::VARIABLE));
        $this->assertFalse($source->appliesToKind(CompletionKind::OBJECT_MEMBER));
        $this->assertFalse($source->appliesToKind(CompletionKind::FUNCTION_NAME));
        $this->assertFalse($source->appliesToKind(CompletionKind::CLASS_NAME));
    }

    public function testReturnsEmptyForEmptyPrefix()
    {
        $history = new History();
        $history->add('phpinfo()');

        $source = new HistorySource($history);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, '');

        $this->assertEmpty($source->getCompletions($analysis));
    }

    public function testReturnsEmptyForEmptyHistory()
    {
        $source = new HistorySource(new History());
        $analysis = new AnalysisResult(CompletionKind::COMMAND, 'php');

        $this->assertEmpty($source->getCompletions($analysis));
    }

    public function testReturnsAllEntries()
    {
        $history = new History();
        $history->add('phpinfo()');
        $history->add('var_dump($x)');
        $history->add('phpversion()');

        $source = new HistorySource($history);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, 'php');

        $completions = $source->getCompletions($analysis);

        $this->assertContains('phpinfo()', $completions);
        $this->assertContains('phpversion()', $completions);
        $this->assertContains('var_dump($x)', $completions);
    }

    public function testMostRecentFirst()
    {
        $history = new History();
        $history->add('phpinfo()');
        $history->add('var_dump($x)');
        $history->add('phpversion()');

        $source = new HistorySource($history);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, 'php');

        $completions = $source->getCompletions($analysis);

        // phpversion() was added last, so it should come first
        $this->assertSame('phpversion()', $completions[0]);
        $this->assertSame('var_dump($x)', $completions[1]);
        $this->assertSame('phpinfo()', $completions[2]);
    }

    public function testDeduplicatesEntries()
    {
        $history = new History();
        $history->add('phpinfo()');
        $history->add('var_dump($x)');
        $history->add('phpinfo()');

        $source = new HistorySource($history);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, 'php');

        $completions = $source->getCompletions($analysis);

        // phpinfo() appears only once even though it was added twice
        $this->assertCount(2, $completions);
        $this->assertSame('phpinfo()', $completions[0]);
        $this->assertSame('var_dump($x)', $completions[1]);
    }

    public function testReturnsAllWithLargeHistory()
    {
        $history = new History();
        for ($i = 0; $i < 30; $i++) {
            $history->add("test_{$i}()");
        }

        $source = new HistorySource($history);
        $analysis = new AnalysisResult(CompletionKind::COMMAND, 'test');

        $completions = $source->getCompletions($analysis);

        // All entries returned; engine handles filtering
        $this->assertCount(30, $completions);
        $this->assertSame('test_29()', $completions[0]);
    }
}
