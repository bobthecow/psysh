<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\TabCompletion\AutoloadWarmer;

use Psy\TabCompletion\AutoloadWarmer\ComposerAutoloadWarmer;
use Psy\Test\TempPaths;
use Psy\Test\TestCase;

class ComposerAutoloadWarmerTest extends TestCase
{
    private static ?\Composer\Autoload\ClassLoader $fixtureLoader = null;
    private static ?string $fixtureVendorDir = null;

    public static function setUpBeforeClass(): void
    {
        $fixtureSource = __DIR__.'/../../Fixtures/autoload-warmer-vendor';
        $fixtureRoot = TempPaths::directory('psysh-autoload-warmer-');
        self::$fixtureVendorDir = $fixtureRoot.'/vendor';
        \mkdir($fixtureRoot.'/src', 0700, true);
        \mkdir($fixtureRoot.'/tests', 0700, true);
        \mkdir(self::$fixtureVendorDir.'/composer', 0700, true);
        \mkdir(self::$fixtureVendorDir.'/tests', 0700, true);

        \copy($fixtureSource.'/../empty.php', $fixtureRoot.'/src/Fixture.php');
        \copy($fixtureSource.'/../empty.php', $fixtureRoot.'/tests/Fixture.php');
        \copy($fixtureSource.'/autoload.php', self::$fixtureVendorDir.'/autoload.php');
        \copy($fixtureSource.'/composer/autoload_real.php', self::$fixtureVendorDir.'/composer/autoload_real.php');
        \copy($fixtureSource.'/composer/autoload_classmap.php', self::$fixtureVendorDir.'/composer/autoload_classmap.php');
        \copy($fixtureSource.'/composer/ClassLoader.php', self::$fixtureVendorDir.'/tests/Fixture.php');

        self::$fixtureLoader = require self::$fixtureVendorDir.'/autoload.php';
        self::$fixtureLoader->register(true);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$fixtureLoader !== null) {
            self::$fixtureLoader->unregister();
        }
    }

    /**
     * Get fixture vendor directory for fast, deterministic testing.
     *
     * Most tests use this minimal fixture instead of the real vendor directory
     * to avoid the performance overhead of scanning hundreds of real packages.
     */
    private function getFixtureVendorDir(): string
    {
        return self::$fixtureVendorDir;
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

        $this->assertContains('Psy\\Shell', $classes);
        $this->assertNotContains('Symfony\\Component\\Console\\Application', $classes);

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

        $this->assertGreaterThan(
            \count($classesWithoutVendor),
            \count($classesWithVendor)
        );
        $this->assertContains('Symfony\\Component\\Console\\Application', $classesWithVendor);
        $this->assertContains('Doctrine\\ORM\\EntityManager', $classesWithVendor);
    }

    public function testIncludeNamespacesFilter()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'     => true,
            'includeNamespaces' => ['Psy\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();

        $this->assertNotEmpty($classes);
        $this->assertContains('Psy\\Shell', $classes);

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
        $this->assertNotEmpty($classes);
        $this->assertContains('Doctrine\\ORM\\EntityManager', $classes);

        // No classes should be from Symfony namespace
        foreach ($classes as $class) {
            $this->assertStringNotContainsString('Symfony\\', $class);
        }
    }

    public function testExcludeTestsByDefault()
    {
        $warmer = new ComposerAutoloadWarmer(['includeVendor' => true], $this->getFixtureVendorDir());
        $classes = $warmer->getClassNames();
        $this->assertNotEmpty($classes);

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

        $this->assertGreaterThan(
            \count($classesWithoutTests),
            \count($classesWithTests)
        );
        $this->assertContains('Psy\\Test\\ShellTest', $classesWithTests);
        $this->assertNotContains('Psy\\Test\\ShellTest', $classesWithoutTests);
        $this->assertContains('Symfony\\Component\\Console\\Tests\\ApplicationTest', $classesWithTests);
        $this->assertNotContains('Symfony\\Component\\Console\\Tests\\ApplicationTest', $classesWithoutTests);
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
        $this->assertNotEmpty($classes);

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
            'includeNamespaces' => ['Psy\\', 'Symfony\\Component\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();

        $this->assertNotEmpty($classes);

        foreach ($classes as $class) {
            $matchesPsy = \strpos($class, 'Psy\\') === 0;
            $matchesSymfony = \strpos($class, 'Symfony\\Component\\Console\\') === 0;
            $this->assertTrue(
                $matchesPsy || $matchesSymfony,
                "Class $class should be in Psy\\ or Symfony\\Component\\Console\\ namespace"
            );
        }
    }

    public function testIncludeVendorNamespacesImpliesIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendorNamespaces' => ['Symfony\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertContains('Symfony\\Component\\Console\\Application', $classes);
        $this->assertNotContains('Doctrine\\ORM\\EntityManager', $classes);
        $this->assertNotContains('PHPUnit\\Framework\\TestCase', $classes);
        $this->assertNotContains('PhpParser\\Parser', $classes);
    }

    public function testIncludeVendorNamespacesFiltersVendorOnly()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendorNamespaces' => ['Symfony\\Component\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertNotEmpty($classes);
        $this->assertContains('Symfony\\Component\\Console\\Application', $classes);
        $this->assertNotContains('Doctrine\\ORM\\EntityManager', $classes);
        $this->assertNotContains('Symfony\\Component\\VarDumper\\VarDumper', $classes);
        $this->assertNotContains('PHPUnit\\Framework\\TestCase', $classes);
        $this->assertNotContains('PhpParser\\Parser', $classes);
    }

    public function testExcludeVendorNamespacesImpliesIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'excludeVendorNamespaces' => ['Symfony\\Component\\VarDumper\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertContains('Symfony\\Component\\Console\\Application', $classes);
        $this->assertContains('Doctrine\\ORM\\EntityManager', $classes);

        // Should include vendor classes
        $vendorCount = 0;
        foreach ($classes as $class) {
            if (\strpos($class, 'Symfony\\') === 0 || \strpos($class, 'Composer\\') === 0) {
                $vendorCount++;
            }
        }

        // Should not include Symfony VarDumper classes
        foreach ($classes as $class) {
            $this->assertNotSame(0, \strpos($class, 'Symfony\\Component\\VarDumper\\'), "Should not include $class");
        }

        $this->assertGreaterThan(0, $vendorCount);
    }

    public function testExcludeVendorNamespacesWithExplicitIncludeVendor()
    {
        $warmer = new ComposerAutoloadWarmer([
            'includeVendor'           => true,
            'excludeVendorNamespaces' => ['Symfony\\Component\\VarDumper\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertNotEmpty($classes);

        // Should not include Symfony\VarDumper classes
        foreach ($classes as $class) {
            $this->assertNotSame(0, \strpos($class, 'Symfony\\Component\\VarDumper\\'), "Should not include $class");
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
            'includeVendorNamespaces' => ['Symfony\\Component\\Console\\'],
        ], $this->getFixtureVendorDir());

        $classes = $warmer->getClassNames();
        $this->assertNotEmpty($classes);
        $this->assertContains('Psy\\TabCompletion\\AutoCompleter', $classes);
        $this->assertContains('Symfony\\Component\\Console\\Application', $classes);

        foreach ($classes as $class) {
            $isPsyTabCompletion = \strpos($class, 'Psy\\TabCompletion\\') === 0;
            $isSymfonyConsole = \strpos($class, 'Symfony\\Component\\Console\\') === 0;
            $this->assertTrue(
                $isPsyTabCompletion || $isSymfonyConsole,
                "Class $class should be Psy\\TabCompletion\\ or Symfony\\Component\\Console\\"
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
