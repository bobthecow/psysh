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

class User
{
    public Address $address;

    public function __construct()
    {
        $this->address = new Address();
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getName(): string
    {
        return 'John Doe';
    }
}
