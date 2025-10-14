<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\CodeCleaner;

use Psy\CodeCleaner\ImplicitUsePass;

/**
 * Tests automatic addition of use statements for unqualified class references
 * when there's a single non-ambiguous match in configured namespaces.
 *
 * @group isolation-fail
 */
class ImplicitUsePassTest extends CodeCleanerTestCase
{
    private const FIXTURE_PREFIX = 'Psy\\Test\\Fixtures\\ImplicitUse';

    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__.'/../fixtures/ImplicitUseFixtures.php';
    }

    /**
     * @dataProvider implicitUseWithIncludeNamespaces
     */
    public function testImplicitUseWithIncludeNamespaces($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function implicitUseWithIncludeNamespaces()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';

        return [
            // Basic unqualified name resolution
            'simple class reference' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $user = new User();',
                "namespace Foo;\n\nuse {$modelNs}\\User;\n\$user = new User();",
            ],

            // Multiple classes in same statement
            'multiple classes' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $user = new User(); $post = new Post();',
                "namespace Foo;\n\nuse {$modelNs}\\Post;\nuse {$modelNs}\\User;\n\$user = new User();\n\$post = new Post();",
            ],

            // Already qualified - should not add use
            'already qualified' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo; \$user = new {$modelNs}\\User();",
                "namespace Foo;\n\n\$user = new {$modelNs}\\User();",
            ],

            // Fully qualified - should not add use
            'fully qualified' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo; \$user = new \\{$modelNs}\\User();",
                "namespace Foo;\n\n\$user = new \\{$modelNs}\\User();",
            ],

            // No namespace in code
            'no namespace' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                '$user = new User();',
                "use {$modelNs}\\User;\n\$user = new User();",
            ],
        ];
    }

    /**
     * @dataProvider implicitUseWithExcludeNamespaces
     */
    public function testImplicitUseWithExcludeNamespaces($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function implicitUseWithExcludeNamespaces()
    {
        $legacyNs = self::FIXTURE_PREFIX.'\\App\\Legacy';
        $appNs = self::FIXTURE_PREFIX.'\\App';

        return [
            // Exclude legacy namespace - User is ambiguous without exclusion, Post is not
            'exclude legacy' => [
                ['includeNamespaces' => [$appNs.'\\'], 'excludeNamespaces' => [$legacyNs.'\\']],
                'namespace Foo; $post = new Post();',
                "namespace Foo;\n\nuse {$appNs}\\Model\\Post;\n\$post = new Post();",
            ],
        ];
    }

    /**
     * @dataProvider implicitUseAmbiguousClasses
     */
    public function testAmbiguousClassesNotAliased($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));

        // Ambiguous classes should remain unqualified (not implicitly aliased)
        $this->assertProcessesAs($code, $code);
    }

    public function implicitUseAmbiguousClasses()
    {
        $appNs = self::FIXTURE_PREFIX.'\\App';

        return [
            // User exists in App\Model, App\View, and App\Legacy (all under App\)
            // Should remain unqualified because it's ambiguous
            'ambiguous user in app namespace' => [
                ['includeNamespaces' => [$appNs.'\\']],
                'namespace Foo; $user = new User();',
            ],
        ];
    }

    /**
     * @dataProvider implicitUseWithExistingUseStatements
     */
    public function testDoesNotConflictWithExistingUse($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function implicitUseWithExistingUseStatements()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $viewNs = self::FIXTURE_PREFIX.'\\App\\View';

        return [
            // Existing use statement - should not add another
            'existing use statement' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\nuse {$viewNs}\\User;\n\$user = new User();",
                "namespace Foo;\n\nuse {$viewNs}\\User;\n\$user = new User();",
            ],

            // Existing alias - should not add implicit use
            'existing alias' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\nuse {$viewNs}\\User as User;\n\$user = new User();",
                "namespace Foo;\n\nuse {$viewNs}\\User as User;\n\$user = new User();",
            ],

            // Different alias name - should add implicit use for unaliased name
            'different alias' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\nuse {$viewNs}\\User as ViewUser;\n\$user = new User();\n\$view = new ViewUser();",
                "namespace Foo;\n\nuse {$modelNs}\\User;\nuse {$viewNs}\\User as ViewUser;\n\$user = new User();\n\$view = new ViewUser();",
            ],
        ];
    }

    /**
     * Should not try to alias classes that already exist in global namespace.
     *
     * @dataProvider implicitUseWithGlobalClasses
     */
    public function testDoesNotAliasGlobalClasses($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($code, $code);
    }

    public function implicitUseWithGlobalClasses()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';

        return [
            // stdClass exists in global namespace
            'global stdClass' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $std = new stdClass();',
            ],

            // Exception exists in global namespace
            'global Exception' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; throw new Exception("test");',
            ],
        ];
    }

    /**
     * @dataProvider implicitUseEdgeCases
     */
    public function testEdgeCases($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function implicitUseEdgeCases()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $domainNs = self::FIXTURE_PREFIX.'\\Domain';

        return [
            // Multiple namespaces in same code
            'multiple namespaces' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\n\$user = new User();\nnamespace Bar;\n\$user = new User();",
                "namespace Foo;\n\nuse {$modelNs}\\User;\n\$user = new User();\nnamespace Bar;\n\nuse {$modelNs}\\User;\n\$user = new User();",
            ],

            // Multiple include namespaces
            'multiple include namespaces' => [
                ['includeNamespaces' => [$modelNs.'\\', $domainNs.'\\']],
                'namespace Foo; $user = new User(); $entity = new DomainEntity();',
                "namespace Foo;\n\nuse {$modelNs}\\User;\nuse {$domainNs}\\DomainEntity;\n\$user = new User();\n\$entity = new DomainEntity();",
            ],

            // Empty config (no filtering) - should do nothing
            'empty config' => [
                [],
                'namespace Foo; $user = new User();',
                "namespace Foo;\n\n\$user = new User();",
            ],
        ];
    }

    /**
     * When in a namespace referencing a class in that namespace, should NOT add use statement
     * (PHP resolves it automatically).
     *
     * @dataProvider currentNamespaceContext
     */
    public function testCurrentNamespaceContext($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($code, $code);
    }

    public function currentNamespaceContext()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $legacyNs = self::FIXTURE_PREFIX.'\\App\\Legacy';

        return [
            // In App\Model namespace, referencing User which exists as App\Model\User
            // Should not add use statement - User resolves to current namespace
            'same namespace as included' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace {$modelNs};\n\$user = new User();",
            ],

            // In App\Legacy namespace, referencing User which exists as App\Legacy\User
            // Even though Legacy is excluded and Model is included, User resolves to current namespace first
            // This is correct PHP behavior - current namespace takes precedence
            'same namespace excluded but current' => [
                ['includeNamespaces' => [$modelNs.'\\'], 'excludeNamespaces' => [$legacyNs.'\\']],
                "namespace {$legacyNs};\n\$user = new User();",
            ],
        ];
    }

    /**
     * @dataProvider caseInsensitivity
     */
    public function testCaseInsensitivity($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function caseInsensitivity()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';

        return [
            // lowercase class name should still resolve
            'lowercase class name' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $post = new post();',
                "namespace Foo;\n\nuse {$modelNs}\\Post;\n\$post = new post();",
            ],

            // Mixed case
            'mixed case class name' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $user = new UsEr();',
                "namespace Foo;\n\nuse {$modelNs}\\User;\n\$user = new UsEr();",
            ],
        ];
    }

    /**
     * @dataProvider variousReferenceContexts
     */
    public function testVariousReferenceContexts($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function variousReferenceContexts()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $contractNs = self::FIXTURE_PREFIX.'\\App\\Contract';
        $traitNs = self::FIXTURE_PREFIX.'\\App\\Traits';
        $exceptionNs = self::FIXTURE_PREFIX.'\\App\\Exception';

        return [
            // Implements interface
            'implements interface' => [
                ['includeNamespaces' => [$contractNs.'\\']],
                'namespace Foo; class MyClass implements UserInterface {}',
                "namespace Foo;\n\nuse {$contractNs}\\UserInterface;\nclass MyClass implements UserInterface\n{\n}",
            ],

            // Extends class
            'extends class' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; class MyUser extends User {}',
                "namespace Foo;\n\nuse {$modelNs}\\User;\nclass MyUser extends User\n{\n}",
            ],

            // Instanceof
            'instanceof check' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $result = $obj instanceof User;',
                "namespace Foo;\n\nuse {$modelNs}\\User;\n\$result = \$obj instanceof User;",
            ],

            // Static call
            'static method call' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; User::find(1);',
                "namespace Foo;\n\nuse {$modelNs}\\User;\nUser::find(1);",
            ],

            // Type hint
            'type hint parameter' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; function foo(User $user) {}',
                "namespace Foo;\n\nuse {$modelNs}\\User;\nfunction foo(User \$user)\n{\n}",
            ],

            // Return type
            'return type' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; function getUser(): User {}',
                "namespace Foo;\n\nuse {$modelNs}\\User;\nfunction getUser() : User\n{\n}",
            ],

            // Catch block
            'catch exception' => [
                ['includeNamespaces' => [$exceptionNs.'\\']],
                'namespace Foo; try {} catch (UserException $e) {}',
                "namespace Foo;\n\nuse {$exceptionNs}\\UserException;\ntry {\n} catch (UserException \$e) {\n}",
            ],

            // Class constant
            'class constant access' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                'namespace Foo; $const = User::CONSTANT;',
                "namespace Foo;\n\nuse {$modelNs}\\User;\n\$const = User::CONSTANT;",
            ],

            // Multiple contexts in one statement
            // Note: Order of use statements may vary (both orderings are valid)
            'multiple contexts' => [
                ['includeNamespaces' => [$contractNs.'\\', $traitNs.'\\']],
                'namespace Foo; class Bar implements UserInterface { use Timestampable; }',
                "namespace Foo;\n\nuse {$contractNs}\\UserInterface;\nuse {$traitNs}\\Timestampable;\nclass Bar implements UserInterface\n{\n    use Timestampable;\n}",
            ],
        ];
    }

    /**
     * Exclude-only configuration includes everything except excluded namespaces.
     *
     * @dataProvider excludeOnlyConfiguration
     */
    public function testExcludeOnlyConfiguration($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function excludeOnlyConfiguration()
    {
        $legacyNs = self::FIXTURE_PREFIX.'\\App\\Legacy';
        $domainNs = self::FIXTURE_PREFIX.'\\Domain';

        return [
            // Only excludeNamespaces, no includeNamespaces (includes everything except excluded)
            'exclude only - matches non-excluded' => [
                ['excludeNamespaces' => [$legacyNs.'\\']],
                'namespace Foo; $entity = new DomainEntity();',
                "namespace Foo;\n\nuse {$domainNs}\\DomainEntity;\n\$entity = new DomainEntity();",
            ],

            // Should NOT include excluded namespace (this case works correctly)
            'exclude only - skips excluded' => [
                ['excludeNamespaces' => [$legacyNs.'\\']],
                'namespace Foo; $old = new OldUser();',
                "namespace Foo;\n\n\$old = new OldUser();",
            ],
        ];
    }

    /**
     * @dataProvider nestedNamespaceMatching
     */
    public function testNestedNamespaceMatching($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function nestedNamespaceMatching()
    {
        $appNs = self::FIXTURE_PREFIX.'\\App';
        $deepNs = self::FIXTURE_PREFIX.'\\App\\Model\\Deep\\Nested';

        return [
            // Prefix matching should work for deeply nested namespaces
            'deeply nested class matches parent prefix' => [
                ['includeNamespaces' => [$appNs.'\\']],
                'namespace Foo; $obj = new DeepClass();',
                "namespace Foo;\n\nuse {$deepNs}\\DeepClass;\n\$obj = new DeepClass();",
            ],
        ];
    }

    /**
     * Group use statements should be recognized as existing aliases.
     *
     * @dataProvider groupUseStatements
     */
    public function testGroupUseStatements($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($code, $code);
    }

    public function groupUseStatements()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';

        return [
            // Group use statement should be recognized as existing aliases
            'group use statement' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\nuse {$modelNs}\\{User, Post};\n\$user = new User();",
            ],
        ];
    }

    /**
     * Exclude should take precedence over include.
     *
     * @dataProvider excludePrecedence
     */
    public function testExcludePrecedence($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($code, $code);
    }

    public function excludePrecedence()
    {
        $appNs = self::FIXTURE_PREFIX.'\\App';
        $legacyNs = self::FIXTURE_PREFIX.'\\App\\Legacy';

        return [
            // Class in App\Legacy matches both App\ (include) and App\Legacy\ (exclude)
            // Exclude should win
            'exclude overrides include' => [
                ['includeNamespaces' => [$appNs.'\\'], 'excludeNamespaces' => [$legacyNs.'\\']],
                'namespace Foo; $old = new OldUser();',
            ],
        ];
    }

    /**
     * @dataProvider traitUseVsImportUse
     */
    public function testTraitUseVsImportUse($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function traitUseVsImportUse()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $traitNs = self::FIXTURE_PREFIX.'\\App\\Traits';

        return [
            // Trait use statement should not interfere with class implicit use
            'trait use vs import use' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\ntrait Bar { use {$traitNs}\\Timestampable; }\n\$user = new User();",
                "namespace Foo;\n\nuse {$modelNs}\\User;\ntrait Bar\n{\n    use {$traitNs}\\Timestampable;\n}\n\$user = new User();",
            ],
        ];
    }

    /**
     * @dataProvider multipleInterfacesAndTraits
     */
    public function testMultipleInterfacesAndTraits($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function multipleInterfacesAndTraits()
    {
        $contractNs = self::FIXTURE_PREFIX.'\\App\\Contract';
        $traitNs = self::FIXTURE_PREFIX.'\\App\\Traits';

        return [
            // Multiple interfaces
            'multiple interfaces' => [
                ['includeNamespaces' => [$contractNs.'\\']],
                'namespace Foo; class Bar implements UserInterface, PostInterface {}',
                "namespace Foo;\n\nuse {$contractNs}\\PostInterface;\nuse {$contractNs}\\UserInterface;\nclass Bar implements UserInterface, PostInterface\n{\n}",
            ],

            // Multiple traits
            'multiple traits' => [
                ['includeNamespaces' => [$traitNs.'\\']],
                'namespace Foo; class Bar { use Timestampable, Sluggable; }',
                "namespace Foo;\n\nuse {$traitNs}\\Sluggable;\nuse {$traitNs}\\Timestampable;\nclass Bar\n{\n    use Timestampable, Sluggable;\n}",
            ],
        ];
    }

    /**
     * @dataProvider ambiguityResolution
     */
    public function testAmbiguityResolution($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function ambiguityResolution()
    {
        $appNs = self::FIXTURE_PREFIX.'\\App';
        $viewNs = self::FIXTURE_PREFIX.'\\App\\View';

        return [
            // User exists in Model, View, and Legacy. Excluding View and Legacy makes it unambiguous
            'exclusion resolves ambiguity' => [
                ['includeNamespaces' => [$appNs.'\\'], 'excludeNamespaces' => [$viewNs.'\\', $appNs.'\\Legacy\\']],
                'namespace Foo; $user = new User();',
                "namespace Foo;\n\nuse {$appNs}\\Model\\User;\n\$user = new User();",
            ],
        ];
    }

    /**
     * @dataProvider multipleNamespacesWithDifferentContexts
     */
    public function testMultipleNamespacesWithDifferentContexts($config, $from, $to)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($from, $to);
    }

    public function multipleNamespacesWithDifferentContexts()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $viewNs = self::FIXTURE_PREFIX.'\\App\\View';

        return [
            // Different namespaces have different contexts
            // Foo namespace only uses ViewUser alias, so no implicit use needed
            // Bar namespace uses unqualified User, so implicit use is added
            'different contexts per namespace' => [
                ['includeNamespaces' => [$modelNs.'\\']],
                "namespace Foo;\nuse {$viewNs}\\User as ViewUser;\n\$view = new ViewUser();\nnamespace Bar;\n\$model = new User();",
                "namespace Foo;\n\nuse {$viewNs}\\User as ViewUser;\n\$view = new ViewUser();\nnamespace Bar;\n\nuse {$modelNs}\\User;\n\$model = new User();",
            ],
        ];
    }

    /**
     * When the same short name exists in multiple included namespaces, it's ambiguous.
     *
     * @dataProvider multipleIncludeNamespacesWithPriority
     */
    public function testMultipleIncludeNamespacesWithPriority($config, $code)
    {
        $this->setPass(new ImplicitUsePass($config));
        $this->assertProcessesAs($code, $code);
    }

    public function multipleIncludeNamespacesWithPriority()
    {
        $modelNs = self::FIXTURE_PREFIX.'\\App\\Model';
        $viewNs = self::FIXTURE_PREFIX.'\\App\\View';

        return [
            // User exists in both Model and View - ambiguous even with both included
            'ambiguous across multiple includes' => [
                ['includeNamespaces' => [$modelNs.'\\', $viewNs.'\\']],
                'namespace Foo; $user = new User();',
            ],
        ];
    }

    /**
     * Test that namespace normalization works correctly.
     */
    public function testNamespaceNormalization()
    {
        // Test with various namespace formats (leading/trailing slashes)
        $pass = new ImplicitUsePass(['includeNamespaces' => ['\\App\\', 'Domain', 'Lib\\\\']]);
        $this->assertInstanceOf(ImplicitUsePass::class, $pass);
    }

    /**
     * Test that pass can be instantiated with no config.
     */
    public function testCanBeInstantiatedWithNoConfig()
    {
        $pass = new ImplicitUsePass();
        $this->assertInstanceOf(ImplicitUsePass::class, $pass);
    }
}
