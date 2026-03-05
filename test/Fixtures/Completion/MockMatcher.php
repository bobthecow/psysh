<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures\Completion;

use Psy\TabCompletion\Matcher\AbstractMatcher;

/**
 * Mock matcher that returns predefined results.
 */
class MockMatcher extends AbstractMatcher
{
    private array $results;
    private bool $shouldMatch;

    public function __construct(array $results, bool $shouldMatch = true)
    {
        $this->results = $results;
        $this->shouldMatch = $shouldMatch;
    }

    public function hasMatched(array $tokens): bool
    {
        return $this->shouldMatch;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        return $this->results;
    }
}
