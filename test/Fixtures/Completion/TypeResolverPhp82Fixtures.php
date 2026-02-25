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

/**
 * Test fixtures for PHP 8.2+ type features (union, intersection, DNF types).
 *
 * This file is loaded conditionally based on PHP version in TypeResolverTest.
 * It contains syntax that cannot be parsed by older PHP versions, so it must be in a separate file.
 */

// Union types (PHP 8.0+)
class UnionTypeRepository
{
    /**
     * Find entity that could be User or Address.
     */
    public function findEntity(int $id): User|Address
    {
        return new User();
    }

    /**
     * Get location, could be City or Country.
     */
    public function getLocation(string $type): City|Country
    {
        return new City();
    }

    /**
     * Union with null (nullable union).
     */
    public function findOptional(int $id): User|null
    {
        return new User();
    }

    /**
     * Three-way union.
     */
    public function getAny(): User|Address|City
    {
        return new User();
    }

    /**
     * Union with scalar (should only return non-scalar types).
     */
    public function getResult(): User|false
    {
        return new User();
    }
}

// Intersection types (PHP 8.1+)
interface Loggable
{
    public function log(): void;
}

interface Timestamped
{
    public function getTimestamp(): int;
}

class LoggableEntity implements Loggable
{
    public function log(): void
    {
    }

    public function save(): void
    {
    }
}

class IntersectionTypeRepository
{
    /**
     * Get object that is both Loggable and Timestamped.
     * For completion, show methods from BOTH interfaces.
     */
    public function getLoggable(): Loggable&Timestamped
    {
        return new class() implements Loggable, Timestamped {
            public function log(): void
            {
            }

            public function getTimestamp(): int
            {
                return 0;
            }
        };
    }

    /**
     * Three-way intersection.
     */
    public function getEntity(): Loggable&Timestamped&\Countable
    {
        return new class() implements Loggable, Timestamped, \Countable {
            public function log(): void
            {
            }

            public function getTimestamp(): int
            {
                return 0;
            }

            public function count(): int
            {
                return 0;
            }
        };
    }
}

// DNF types (PHP 8.2+)
class DnfTypeRepository
{
    /**
     * DNF type: (A&B)|C
     * Should return all mentioned types for completion.
     */
    public function getComplex(): (Loggable&Timestamped)|User
    {
        return new User();
    }

    /**
     * More complex DNF: (A&B)|(C&D).
     */
    public function getVeryComplex(): (Loggable&Timestamped)|(User&\Countable)
    {
        return new class() implements Loggable, Timestamped {
            public function log(): void
            {
            }

            public function getTimestamp(): int
            {
                return 0;
            }
        };
    }
}
