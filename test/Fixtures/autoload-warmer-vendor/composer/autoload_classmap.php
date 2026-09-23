<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Minimal classmap fixture for testing ComposerAutoloadWarmer filtering logic.
 *
 * This fixture includes representative classes from different categories:
 * - Application classes (Psy\*)
 * - Vendor classes (Symfony\*, Doctrine\*, etc.)
 * - Test classes (*\Test\*, *\Tests\*)
 * - Various namespace depths
 */

$applicationFixture = \dirname(__DIR__, 2).'/src/Fixture.php';
$applicationTestFixture = \dirname(__DIR__, 2).'/tests/Fixture.php';
$vendorFixture = __DIR__.'/autoload_classmap.php';
$vendorTestFixture = \dirname(__DIR__).'/tests/Fixture.php';

return [
    // Application classes - should be included by default
    'Psy\\Shell'                                       => $applicationFixture,
    'Psy\\Configuration'                               => $applicationFixture,
    'Psy\\CodeCleaner'                                 => $applicationFixture,
    'Psy\\TabCompletion\\AutoCompleter'                => $applicationFixture,
    'Psy\\TabCompletion\\Matcher\\ClassMethodsMatcher' => $applicationFixture,
    'Psy\\Command\\ListCommand'                        => $applicationFixture,
    'Psy\\Command\\HelpCommand'                        => $applicationFixture,

    // Application test classes - should be excluded by default
    'Psy\\Test\\TestCase'         => $applicationTestFixture,
    'Psy\\Test\\ShellTest'        => $applicationTestFixture,
    'Psy\\Tests\\CodeCleanerTest' => $applicationTestFixture,

    // Symfony vendor classes
    'Symfony\\Component\\Console\\Application'           => $vendorFixture,
    'Symfony\\Component\\Console\\Command\\Command'      => $vendorFixture,
    'Symfony\\Component\\Console\\Input\\InputInterface' => $vendorFixture,
    'Symfony\\Component\\VarDumper\\Dumper\\CliDumper'   => $vendorFixture,
    'Symfony\\Component\\VarDumper\\VarDumper'           => $vendorFixture,

    // Symfony test classes - vendor tests, should be excluded
    'Symfony\\Component\\Console\\Tests\\ApplicationTest' => $vendorTestFixture,

    // Doctrine vendor classes
    'Doctrine\\Common\\Collections\\Collection' => $vendorFixture,
    'Doctrine\\ORM\\EntityManager'              => $vendorFixture,

    // Doctrine test classes
    'Doctrine\\Tests\\Common\\CollectionTest' => $vendorTestFixture,

    // PHPUnit vendor classes
    'PHPUnit\\Framework\\TestCase' => $vendorFixture,
    'PHPUnit\\Runner\\TestRunner'  => $vendorFixture,

    // nikic/php-parser vendor classes
    'PhpParser\\Parser'     => $vendorFixture,
    'PhpParser\\Node\\Stmt' => $vendorFixture,

    // Monolog vendor classes
    'Monolog\\Logger'                 => $vendorFixture,
    'Monolog\\Handler\\StreamHandler' => $vendorFixture,
];
