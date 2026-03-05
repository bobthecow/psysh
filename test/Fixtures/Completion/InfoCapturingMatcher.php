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
 * Matcher that captures readline_info for verification.
 */
class InfoCapturingMatcher extends AbstractMatcher
{
    private array $lastInfo = [];

    public function hasMatched(array $tokens): bool
    {
        return true;
    }

    public function getMatches(array $tokens, array $info = []): array
    {
        $this->lastInfo = $info;

        return [];
    }

    public function getLastInfo(): array
    {
        return $this->lastInfo;
    }
}
