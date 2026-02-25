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

class Collection
{
    public function filter(callable $callback): self
    {
        return $this;
    }

    public function map(callable $callback): self
    {
        return $this;
    }

    public function first(): User
    {
        return new User();
    }

    public function slice(int $offset, ?int $length = null): self
    {
        return $this;
    }
}
