<?php

/*
 * Minimal classmap fixture for testing ComposerAutoloadWarmer filtering logic.
 *
 * This fixture includes representative classes from different categories:
 * - Application classes (Psy\*)
 * - Vendor classes (Symfony\*, Doctrine\*, etc.)
 * - Test classes (*\Test\*, *\Tests\*)
 * - Various namespace depths
 */

return [
    // Application classes - should be included by default
    'Psy\\Shell'                                       => __FILE__,
    'Psy\\Configuration'                               => __FILE__,
    'Psy\\CodeCleaner'                                 => __FILE__,
    'Psy\\TabCompletion\\AutoCompleter'                => __FILE__,
    'Psy\\TabCompletion\\Matcher\\ClassMethodsMatcher' => __FILE__,
    'Psy\\Command\\ListCommand'                        => __FILE__,
    'Psy\\Command\\HelpCommand'                        => __FILE__,

    // Application test classes - should be excluded by default
    'Psy\\Test\\TestCase'         => __FILE__,
    'Psy\\Test\\ShellTest'        => __FILE__,
    'Psy\\Tests\\CodeCleanerTest' => __FILE__,

    // Symfony vendor classes
    'Symfony\\Component\\Console\\Application'           => __FILE__,
    'Symfony\\Component\\Console\\Command\\Command'      => __FILE__,
    'Symfony\\Component\\Console\\Input\\InputInterface' => __FILE__,
    'Symfony\\Component\\VarDumper\\Dumper\\CliDumper'   => __FILE__,
    'Symfony\\Component\\VarDumper\\VarDumper'           => __FILE__,

    // Symfony test classes - vendor tests, should be excluded
    'Symfony\\Component\\Console\\Tests\\ApplicationTest' => __FILE__,

    // Doctrine vendor classes
    'Doctrine\\Common\\Collections\\Collection' => __FILE__,
    'Doctrine\\ORM\\EntityManager'              => __FILE__,

    // Doctrine test classes
    'Doctrine\\Tests\\Common\\CollectionTest' => __FILE__,

    // PHPUnit vendor classes
    'PHPUnit\\Framework\\TestCase' => __FILE__,
    'PHPUnit\\Runner\\TestRunner'  => __FILE__,

    // nikic/php-parser vendor classes
    'PhpParser\\Parser'     => __FILE__,
    'PhpParser\\Node\\Stmt' => __FILE__,

    // Monolog vendor classes
    'Monolog\\Logger'                 => __FILE__,
    'Monolog\\Handler\\StreamHandler' => __FILE__,

    // Deeply nested classes for testing prefix matching
    'Psy\\TabCompletion\\Matcher\\Helper\\Deep\\Nested\\Class'  => __FILE__,
    'Symfony\\Component\\Console\\Helper\\Deep\\Nested\\Helper' => __FILE__,
];
