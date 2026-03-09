<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Completion;

use Psy\Completion\FuzzyMatcher;
use Psy\Test\TestCase;

class FuzzyMatcherTest extends TestCase
{
    public function testExactPrefixMatch()
    {
        $candidates = ['array_sum', 'array_map', 'array_filter', 'count'];
        $result = FuzzyMatcher::filter('array', $candidates);

        $this->assertContains('array_sum', $result);
        $this->assertContains('array_map', $result);
        $this->assertContains('array_filter', $result);
        $this->assertNotContains('count', $result);
    }

    public function testFuzzyMatch()
    {
        $candidates = ['array_sum', 'array_map', 'array_filter', 'array_reduce'];
        $result = FuzzyMatcher::filter('asum', $candidates);

        $this->assertContains('array_sum', $result);
        $this->assertNotContains('array_map', $result);
        $this->assertNotContains('array_filter', $result);
    }

    public function testFuzzyMatchMultipleResults()
    {
        $candidates = ['strtolower', 'strtoupper', 'strtotime', 'str_replace'];
        $result = FuzzyMatcher::filter('stl', $candidates);

        $this->assertContains('strtolower', $result);
        $this->assertNotContains('strtoupper', $result);
    }

    public function testCaseInsensitiveMatching()
    {
        $candidates = ['ArrayException', 'ArrayIterator', 'Exception'];
        $result = FuzzyMatcher::filter('ae', $candidates);

        $this->assertContains('ArrayException', $result);
    }

    public function testEmptySearchReturnsAll()
    {
        $candidates = ['array_sum', 'count', 'strlen'];
        $result = FuzzyMatcher::filter('', $candidates);

        $this->assertEquals($candidates, $result);
    }

    public function testNoMatchesReturnsEmpty()
    {
        $candidates = ['array_sum', 'count', 'strlen'];
        $result = FuzzyMatcher::filter('xyz', $candidates);

        $this->assertEmpty($result);
    }

    public function testMatchPrioritization()
    {
        $candidates = ['array_sum', 'a_sum_function', 'another_sum'];
        $result = FuzzyMatcher::filter('asum', $candidates);

        $this->assertCount(3, $result);
        $this->assertContains('array_sum', $result);
        $this->assertContains('a_sum_function', $result);
        $this->assertContains('another_sum', $result);

        // Better consecutive matching scores higher
        $this->assertEquals('a_sum_function', $result[0]);
    }

    public function testSubstringMatchBeforeFuzzy()
    {
        $candidates = ['some_function', 'sum_function', 'array_sum'];
        $result = FuzzyMatcher::filter('sum', $candidates);

        $this->assertContains('sum_function', $result);
        $this->assertContains('array_sum', $result);
        $this->assertEquals('sum_function', $result[0]);
    }

    public function testPrefixMatchesFirst()
    {
        $candidates = ['array_sum', 'sum_array', 'my_array_sum'];
        $result = FuzzyMatcher::filter('array', $candidates);

        $this->assertEquals('array_sum', $result[0]);
    }

    public function testMatchesMethod()
    {
        // Test the matches() helper method
        $this->assertTrue(FuzzyMatcher::matches('asum', 'array_sum'));
        $this->assertTrue(FuzzyMatcher::matches('stl', 'strtolower'));
        $this->assertTrue(FuzzyMatcher::matches('ae', 'ArrayException'));

        $this->assertFalse(FuzzyMatcher::matches('xyz', 'array_sum'));
        $this->assertFalse(FuzzyMatcher::matches('ba', 'array_sum')); // wrong order
    }

    public function testConsecutiveCharacterBonus()
    {
        $candidates = ['array_sum', 'a_r_r_a_y_sum'];
        $result = FuzzyMatcher::filter('array', $candidates);

        // Consecutive characters score better
        $this->assertEquals('array_sum', $result[0]);
    }

    public function testRealWorldFunctionExamples()
    {
        $phpFunctions = [
            'array_sum',
            'array_map',
            'array_filter',
            'array_reduce',
            'array_key_exists',
            'count',
            'strlen',
            'strtolower',
            'strtoupper',
            'str_replace',
            'preg_match',
            'file_get_contents',
        ];

        // Test common fuzzy searches
        $result = FuzzyMatcher::filter('asum', $phpFunctions);
        $this->assertContains('array_sum', $result);
        $this->assertEquals('array_sum', $result[0]); // Should be first

        $result = FuzzyMatcher::filter('ake', $phpFunctions);
        $this->assertContains('array_key_exists', $result);

        $result = FuzzyMatcher::filter('fgc', $phpFunctions);
        $this->assertContains('file_get_contents', $result);

        $result = FuzzyMatcher::filter('stl', $phpFunctions);
        $this->assertContains('strtolower', $result);
    }

    public function testAlphabeticalSortingForSameScore()
    {
        // When scores are equal, should sort alphabetically
        $candidates = ['zebra_test', 'apple_test'];
        $result = FuzzyMatcher::filter('test', $candidates);

        // Both match with same score, so alphabetical order
        $this->assertEquals('apple_test', $result[0]);
        $this->assertEquals('zebra_test', $result[1]);
    }

    public function testSpecialCharactersInCandidates()
    {
        $candidates = ['$myVariable', '$yourVariable', 'myFunction'];
        $result = FuzzyMatcher::filter('myv', $candidates);

        // Should match '$myVariable'
        $this->assertContains('$myVariable', $result);
    }

    public function testUnderscoreSeparatedWords()
    {
        $candidates = [
            'get_user_name',
            'set_user_name',
            'get_user_email',
            'delete_user',
        ];

        $result = FuzzyMatcher::filter('gun', $candidates);
        $this->assertContains('get_user_name', $result);

        $result = FuzzyMatcher::filter('gue', $candidates);
        $this->assertContains('get_user_email', $result);
    }

    public function testEmptyCandidatesArray()
    {
        $result = FuzzyMatcher::filter('test', []);
        $this->assertEmpty($result);
    }

    public function testSingleCandidate()
    {
        $result = FuzzyMatcher::filter('test', ['testing']);
        $this->assertContains('testing', $result);

        $result = FuzzyMatcher::filter('xyz', ['testing']);
        $this->assertEmpty($result);
    }
}
