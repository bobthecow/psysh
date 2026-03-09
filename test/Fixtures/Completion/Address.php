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

class Address
{
    public string $street = '123 Main St';
    public City $city;

    public function __construct()
    {
        $this->city = new City();
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function getStreet(): string
    {
        return $this->street;
    }
}
