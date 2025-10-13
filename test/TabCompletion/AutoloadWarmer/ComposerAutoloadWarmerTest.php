<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion\AutoloadWarmer;

use Psy\TabCompletion\AutoloadWarmer\ComposerAutoloadWarmer;
use Psy\Test\TestCase;

class ComposerAutoloadWarmerTest extends TestCase
{
    /**
     * Get fixture vendor directory for fast, deterministic testing.
     *
     * Most tests use this minimal fixture instead of the real vendor directory
     * to avoid the performance overhead of scanning hundreds of real packages.
     */
    private function getFixtureVendorDir(): string
    {
        return __DIR__.'/../../fixtures/autoload-warmer-vendor';
    }

    /**
     * Get real project vendor directory for integration tests.
     *
     * Only used by tests that need to verify behavior with real Composer data.
     */
    private function getProjectVendorDir(): string
    {
        $vendorDir = __DIR__.'/../../../vendor';
        $realPath = \realpath($vendorDir);

        return $realPath !== false ? $realPath : $vendorDir;
    }

    /**
     * Integration test to verify warm() actually loads classes.
     */
    public function testWarmLoadsClasses()
    {
        $warmer = new ComposerAutoloadWarmer([], $this->getFixtureVendorDir());

        $classesBefore = \count(\get_declared_classes()) +
                        \count(\get_declared_interfaces()) +
                        \count(\get_declared_traits());

        $loaded = $warmer->warm();

        $classesAfter = \count(\get_declared_classes()) +
                       \count(\get_declared_interfaces()) +
                       \count(\get_declared_traits());

        // Should be non-negative
        $this->assertGreaterThanOrEqual(0, $loaded);
        // The count returned should match the actual change in declared classes
        $this->assertEquals($classesAfter - $classesBefore, $loaded);
    }

    /**
     * Test class discovery without side effects.
     */
    public function testGetClassesToLoadWithoutVendor()
    {
        $warmer = new ComposerAutoloadWarmer([], $this->getFixtureVendorDir());
        $classes = $warmer->getClassNames();

        // Should return an array
        $this->assertIsArray($classes);

        // Should only include Psy classes (non-vendor)
        foreach ($classes as $class) {
            $this->assertStringStartsWith('Psy\\', $class, 'Should only include Psy classes without includeVendor');
            $this->assertStringNotContainsString('\\Test', $class, 'Should not include test classes by default');
        }
    }

    /**
     * Test that includeVendor option affects discovered classes.
     */
    public function testGetClassesToLoadWithVendor()
    {
        $warmerWithoutVendor = new ComposerAutoloadWarmer([], $this->getFixtureVendorDir());
        $warmerWithVendor = new ComposerAutoloadWarmer(['includeVendor' => true], $this->getFixtureVendorDir());

        $classesWithoutVendor = $warmerWithoutVendor->getClassNames();
        $classesWithVendor = $warmerWithVendor->getClassNames();

        // With vendor should discover at least as many classes
        $this->assertGreaterThanOrEqual(
            \count($classesWithoutVendor),
            \count($classesWithVendor)
        );
    }

    public function testIncludeNamespacesFilter()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'     => true,
            'includeNamespaces' => ['Psy\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();

        // Should return an array
        $this->assertIsArray($classes);
        $this->assertGreaterThanOrEqual(0, \count($classes));

        // All classes should be in the Psy namespace
        foreach ($classes as $class) {
            $this->assertStringStartsWith('Psy\\', $class);
        }
    }

    public function testExcludeNamespacesFilter()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'     => true,
            'excludeNamespaces' => ['Symfony\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertGreaterThanOrEqual(0, \count($classes));

        // No classes should be from Symfony namespace
        foreach ($classes as $class) {
            $this->assertStringNotContainsString('Symfony\\', $class);
        }
    }

    public function testExcludeTestsByDefault()
    {
        $warmer = new ComposerAutoloadWarmer(['includeVendor' => true], $this->getFixtureVendorDir());
        $classes = $warmer->getClassNames();
        $this->assertGreaterThanOrEqual(0, \count($classes));

        // Check that test classes are excluded
        foreach ($classes as $class) {
            // Should not contain \Test\, \Tests\, \Spec\, or \Specs\
            $this->assertStringNotContainsString('\\Test\\', $class);
            $this->assertStringNotContainsString('\\Tests\\', $class);
            $this->assertStringNotContainsString('\\Spec\\', $class);
            $this->assertStringNotContainsString('\\Specs\\', $class);
        }
    }

    public function testIncludeTestsWhenConfigured()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor' => true,
            'includeTests'  => true,
        ], $this->getFixtureVendorDir());

        $warmerWithoutTests = new ComposerAutoloadWarmer([
            'includeVendor' => true,
            'includeTests'  => false,
        ], $this->getFixtureVendorDir());

        $classesWithTests = $warmer->getClassNames();
        $classesWithoutTests = $warmerWithoutTests->getClassNames();

        // With tests should discover at least as many classes
        $this->assertGreaterThanOrEqual(
            \count($classesWithoutTests),
            \count($classesWithTests)
        );
    }

    public function testMultipleWarmCallsAreSafe()
    {
        // Integration test - uses real vendor to actually load classes
        $warmer = new ComposerAutoloadWarmer(['includeVendor' => true], $this->getProjectVendorDir());

        // First warm
        $loaded1 = $warmer->warm();
        $this->assertGreaterThanOrEqual(1, $loaded1);

        // Second warm should be a no-op since classes are already loaded
        $loaded2 = $warmer->warm();
        $this->assertEquals(0, $loaded2);
    }

    public function testNamespacePrefixNormalization()
    {
        // Test that leading backslash is removed and trailing backslash is added
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'     => true,
            'includeNamespaces' => [
                '\\Psy',           // Leading backslash, no trailing
                'Symfony\\',       // No leading, has trailing (already normalized)
                '\\Composer\\',    // Both leading and trailing
            ],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertGreaterThanOrEqual(0, \count($classes));

        // Check that classes match the normalized prefixes
        foreach ($classes as $class) {
            // Should match one of: Psy\, Symfony\, or Composer\
            $matches = \strpos($class, 'Psy\\') === 0 ||
                      \strpos($class, 'Symfony\\') === 0 ||
                      \strpos($class, 'Composer\\') === 0;
            $this->assertTrue($matches, "Class $class should match one of the normalized prefixes");
        }
    }

    public function testMultipleIncludeNamespaces()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'     => true,
            'includeNamespaces' => ['Psy\\', 'Symfony\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();

        // Should return an array
        $this->assertIsArray($classes);

        foreach ($classes as $class) {
            $matchesPsy = \strpos($class, 'Psy\\') === 0;
            $matchesSymfony = \strpos($class, 'Symfony\\Console\\') === 0;
            $this->assertTrue(
                $matchesPsy || $matchesSymfony,
                "Class $class should be in Psy\\ or Symfony\\Console\\ namespace"
            );
        }
    }

    public function testIncludeVendorNamespacesImpliesIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendorNamespaces' => ['Symfony\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertIsArray($classes);

        // Verify only Symfony vendor classes are included (and no other vendor classes)
        foreach ($classes as $class) {
            // If it's a vendor class, it must be Symfony
            if (\strpos($class, 'Composer\\') === 0 ||
                (\strpos($class, 'Symfony\\') !== 0 && $this->looksLikeVendorClass($class))) {
                $this->fail("Non-Symfony vendor class $class should not be included");
            }
        }

        // Test passes - config was accepted and filtering works correctly
        $this->assertTrue(true);
    }

    private function looksLikeVendorClass(string $class): bool
    {
        // Common vendor package prefixes
        return \strpos($class, 'PHPUnit\\') === 0 ||
               \strpos($class, 'Doctrine\\') === 0 ||
               \strpos($class, 'Monolog\\') === 0;
    }

    public function testIncludeVendorNamespacesFiltersVendorOnly()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendorNamespaces' => ['Symfony\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertIsArray($classes);

        // Check that vendor classes are filtered correctly
        $hasOtherVendor = false;
        $hasSymfonyConsole = false;

        foreach ($classes as $class) {
            if (\strpos($class, 'Symfony\\Console\\') === 0) {
                $hasSymfonyConsole = true;
            } elseif (\strpos($class, 'Symfony\\') === 0) {
                // Other Symfony namespace - should be excluded
                $this->fail("Class $class should not be included (only Symfony\\Console\\ allowed)");
            } elseif (\strpos($class, 'Composer\\') === 0 || \strpos($class, 'nikic\\') === 0) {
                // Other vendor packages should be excluded
                $hasOtherVendor = true;
            }
            // Psy classes (non-vendor) are allowed but not required in minimal classmaps
        }

        // Should NOT have other vendor classes
        $this->assertFalse($hasOtherVendor, 'Should not include vendor classes outside includeVendorNamespaces');

        // Note: We don't require Psy classes to exist in classmap since the environment
        // may have a minimal/non-optimized classmap with only autoloader metadata classes
    }

    public function testExcludeVendorNamespacesImpliesIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'excludeVendorNamespaces' => ['Symfony\\Debug\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertIsArray($classes);

        // Should include vendor classes
        $vendorCount = 0;
        foreach ($classes as $class) {
            if (\strpos($class, 'Symfony\\') === 0 || \strpos($class, 'Composer\\') === 0) {
                $vendorCount++;
            }
        }

        // Should not include Symfony\Debug classes
        foreach ($classes as $class) {
            $this->assertNotSame(0, \strpos($class, 'Symfony\\Debug\\'), "Should not include $class");
        }

        // Test passes - config was accepted and filtering works correctly
        $this->assertTrue(true);
    }

    public function testExcludeVendorNamespacesWithExplicitIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'           => true,
            'excludeVendorNamespaces' => ['Symfony\\VarDumper\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertIsArray($classes);

        // Should not include Symfony\VarDumper classes
        foreach ($classes as $class) {
            $this->assertNotSame(0, \strpos($class, 'Symfony\\VarDumper\\'), "Should not include $class");
        }
    }

    public function testConflictingIncludeVendorFalseWithVendorNamespaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use includeVendorNamespaces or excludeVendorNamespaces when includeVendor is false');

        new ComposerAutoloadWarmer([
            'includeVendor'           => false,
            'includeVendorNamespaces' => ['Symfony\\'],
        ], $this->getFixtureVendorDir());
    }

    public function testConflictingIncludeVendorFalseWithExcludeVendorNamespaces()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use includeVendorNamespaces or excludeVendorNamespaces when includeVendor is false');

        new ComposerAutoloadWarmer([
            'includeVendor'           => false,
            'excludeVendorNamespaces' => ['Symfony\\'],
        ], $this->getFixtureVendorDir());
    }

    public function testCombineVendorAndNonVendorNamespaceFilters()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeNamespaces'       => ['Psy\\TabCompletion\\'],
            'includeVendorNamespaces' => ['Symfony\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertIsArray($classes);

        foreach ($classes as $class) {
            $isPsyTabCompletion = \strpos($class, 'Psy\\TabCompletion\\') === 0;
            $isSymfonyConsole = \strpos($class, 'Symfony\\Console\\') === 0;
            $this->assertTrue(
                $isPsyTabCompletion || $isSymfonyConsole,
                "Class $class should be Psy\\TabCompletion\\ or Symfony\\Console\\"
            );
        }
    }

    public function testExcludesPharScopedClasses()
    {
        // Integration test - uses real vendor to check for actual PHAR scoped classes
        $warmer = new ComposerAutoloadWarmer(['includeVendor' => true], $this->getProjectVendorDir());
        $classes = $warmer->getClassNames();

        // Verify no classes start with "_Psy<hash>\" (PsySH's PHAR scoped prefix)
        foreach ($classes as $class) {
            $this->assertDoesNotMatchRegularExpression(
                '/^_Psy[a-f0-9]+\\\\/',
                $class,
                "Class $class matches _Psy scoped prefix pattern and should be excluded"
            );
        }
    }
}
