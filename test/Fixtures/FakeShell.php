<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Fixtures;

use Psy\Shell;

class FakeShell extends Shell
{
    public $matchers;

    public function __construct()
    {
    }

    public function addMatchers(array $matchers)
    {
        $matchers = $this->deduplicateObjects($matchers, $this->matchers ?? []);
        $this->matchers = \array_merge($this->matchers ?? [], $matchers);
    }

    public function addCommands(array $commands): void
    {
    }

    public function addCompletionSources(array $sources)
    {
    }
}
