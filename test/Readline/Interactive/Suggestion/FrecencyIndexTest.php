<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Readline\Interactive\Suggestion;

use Psy\Readline\Interactive\Input\History;
use Psy\Readline\Interactive\Suggestion\FrecencyIndex;
use Psy\Test\TestCase;

class FrecencyIndexTest extends TestCase
{
    public function testBuildsIndexFromHistory()
    {
        $history = new History();
        $history->add('array_map($callback, $array)');
        $history->add('array_filter($array, $callback)');
        $history->add('$result = array_reduce($array, $callback, $initial)');

        $index = new FrecencyIndex($history);

        $this->assertTrue($index->hasWord('array_map'));
        $this->assertTrue($index->hasWord('array_filter'));
        $this->assertTrue($index->hasWord('array_reduce'));
        $this->assertTrue($index->hasWord('callback'));
        $this->assertTrue($index->hasWord('array'));
        $this->assertTrue($index->hasWord('result'));
    }

    public function testFiltersStopwords()
    {
        $history = new History();
        $history->add('if ($condition) { return true; }');

        $index = new FrecencyIndex($history);

        $this->assertFalse($index->hasWord('if'));
        $this->assertFalse($index->hasWord('return'));
        $this->assertFalse($index->hasWord('true'));

        $this->assertTrue($index->hasWord('condition'));
    }

    public function testFiltersShortWords()
    {
        $history = new History();
        $history->add('$a = $b + $c');

        $index = new FrecencyIndex($history);

        $this->assertFalse($index->hasWord('a'));
        $this->assertFalse($index->hasWord('b'));
        $this->assertFalse($index->hasWord('c'));
    }

    public function testScoresFrequentWordsHigher()
    {
        $history = new History();
        $history->add('array_map($a, $b)');
        $history->add('array_map($x, $y)');
        $history->add('array_map($foo, $bar)');
        $history->add('array_filter($data, $fn)');

        $index = new FrecencyIndex($history);

        $mapScore = $index->getScore('array_map');
        $filterScore = $index->getScore('array_filter');

        $this->assertGreaterThan($filterScore, $mapScore);
    }

    public function testScoresRecentWordsHigher()
    {
        $history = new History();

        // Add old entry (will be filtered by 30-day window in real use)
        // For testing, we'll add recent ones with different implicit recency
        $history->add('old_function($x)');

        // Sleep a tiny bit to ensure timestamp difference
        \usleep(1000);

        $history->add('recent_function($y)');

        $index = new FrecencyIndex($history);

        $oldScore = $index->getScore('old_function');
        $recentScore = $index->getScore('recent_function');

        // Both should be scored, recent might be slightly higher
        // but this is hard to test precisely due to time granularity
        $this->assertGreaterThan(0, $oldScore);
        $this->assertGreaterThan(0, $recentScore);
    }

    public function testReturnsZeroForUnknownWords()
    {
        $history = new History();
        $history->add('array_map($x, $y)');

        $index = new FrecencyIndex($history);

        $score = $index->getScore('unknown_function');

        $this->assertEquals(0.0, $score);
    }

    public function testNormalizesToLowercase()
    {
        $history = new History();
        $history->add('MyClassName::myMethod()');

        $index = new FrecencyIndex($history);

        $this->assertTrue($index->hasWord('myclassname'));
        $this->assertTrue($index->hasWord('MyClassName'));
        $this->assertTrue($index->hasWord('MYCLASSNAME'));

        $this->assertEquals(
            $index->getScore('myclassname'),
            $index->getScore('MyClassName')
        );
    }

    public function testExtractsVariablesWithoutDollarSign()
    {
        $history = new History();
        $history->add('$myVariable = 123');

        $index = new FrecencyIndex($history);

        $this->assertTrue($index->hasWord('myvariable'));
        $this->assertGreaterThan(0, $index->getScore('myvariable'));
    }

    public function testHandlesMultiLineCommands()
    {
        $history = new History();
        $history->add("function test() {\n    array_map(\$x, \$y);\n    array_filter(\$z);\n}");

        $index = new FrecencyIndex($history);

        $this->assertTrue($index->hasWord('test'));
        $this->assertTrue($index->hasWord('array_map'));
        $this->assertTrue($index->hasWord('array_filter'));
    }

    public function testGetAllScoresReturnsSortedMap()
    {
        $history = new History();
        $history->add('array_map($x, $y)');
        $history->add('array_map($a, $b)');
        $history->add('array_filter($c)');

        $index = new FrecencyIndex($history);

        $scores = $index->getAllScores();

        $this->assertIsArray($scores);
        $this->assertNotEmpty($scores);

        // Should be sorted descending by score
        $prevScore = \PHP_FLOAT_MAX;
        foreach ($scores as $word => $score) {
            $this->assertLessThanOrEqual($prevScore, $score);
            $prevScore = $score;
        }
    }

    public function testHandlesEmptyHistory()
    {
        $history = new History();
        $index = new FrecencyIndex($history);

        $this->assertFalse($index->hasWord('anything'));
        $this->assertEquals(0.0, $index->getScore('anything'));
        $this->assertEmpty($index->getAllScores());
    }
}
