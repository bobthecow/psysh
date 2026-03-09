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

class CompletionEngineFactoryFixture
{
    public static function create(): self
    {
        return new self();
    }

    public function format(): string
    {
        return 'ok';
    }
}
