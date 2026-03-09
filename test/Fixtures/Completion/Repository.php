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

class Repository
{
    public function find(int $id): User
    {
        return new User();
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): User
    {
        return new User();
    }

    public function findWhere(callable $callback): User
    {
        return new User();
    }

    public function query(string $sql, array $params = [], bool $cache = false): User
    {
        return new User();
    }
}
