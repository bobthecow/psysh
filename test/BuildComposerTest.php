<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

/**
 * @group isolation-fail
 */
class BuildComposerTest extends TestCase
{
    private static $mainComposer;
    private static $buildComposer;

    public static function setUpBeforeClass(): void
    {
        self::$mainComposer = \json_decode(\file_get_contents(__DIR__.'/../composer.json'), true);
        self::$buildComposer = \json_decode(\file_get_contents(__DIR__.'/../build/composer.json'), true);
    }

    /**
     * @dataProvider mainDependencyProvider
     */
    public function testBuildIncludesMainDependencies(string $package, string $mainConstraint)
    {
        $buildConstraint = self::$buildComposer['require'][$package] ?? null;

        $this->assertNotNull($buildConstraint, "Package {$package} from main composer.json missing from build/composer.json");
        $this->assertSame($mainConstraint, $buildConstraint, "Version constraint mismatch for {$package}");
    }

    public static function mainDependencyProvider(): array
    {
        $main = \json_decode(\file_get_contents(__DIR__.'/../composer.json'), true);

        $cases = [];
        foreach ($main['require'] as $package => $constraint) {
            $cases[$package] = [$package, $constraint];
        }

        return $cases;
    }

    public function testConflictsMatch()
    {
        $mainConflicts = self::$mainComposer['conflict'] ?? [];
        $buildConflicts = self::$buildComposer['conflict'] ?? [];

        $this->assertSame($mainConflicts, $buildConflicts, 'Conflict constraints should match');
    }
}
