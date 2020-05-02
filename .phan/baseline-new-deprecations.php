<?php

/**
 * Baseline for issues that only appear with latest dependency versions.
 *
 * These are FUTURE DEPRECATIONS that occur when testing with:
 *   - nikic/php-parser: ^5.0+ (latest)
 *   - symfony/console: ^5.0+ (latest)
 *
 * Main issues:
 *   - php-parser 5.0 deprecated UseUse → UseItem, LNumber → Int_, DeclareDeclare → DeclareItem
 *   - php-parser 5.0 changed ->parts property to getParts() method on Node\Name
 *   - Symfony Console removed some old methods (asText(), etc.)
 *
 * WHEN TO REVIEW THIS FILE:
 *   - When dropping support for php-parser 4.x (can fix these deprecations)
 *   - When dropping support for symfony 3.x/4.x (can use newer APIs)
 */
return [
    'file_suppressions' => [
        'src/CodeCleaner.php' => ['PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchReturnNullable', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/CalledClassPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/ImplicitUsePass.php' => ['PhanDeprecatedClass', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/NamespaceAwarePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/NamespacePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/RequirePass.php' => ['PhanDeprecatedClass'],
        'src/CodeCleaner/StrictTypesPass.php' => ['PhanDeprecatedClass'],
        'src/CodeCleaner/UseStatementPass.php' => ['PhanDeprecatedClass', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference'],
        'src/CodeCleaner/ValidConstructorPass.php' => ['PhanUndeclaredProperty'],
        'src/Command/CodeArgumentParser.php' => ['PhanTypeMismatchReturnNullable'],
        'src/Command/Command.php' => ['PhanUndeclaredMethod'],
        'src/Command/HelpCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/HistoryCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/ParseCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/ShowCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/TraceCommand.php' => ['PhanUndeclaredMethod'],
        'src/Formatter/TraceFormatter.php' => ['PhanTypeMismatchArgumentNullable'],
        'src/ParserFactory.php' => ['PhanUndeclaredConstantOfClass', 'PhanUndeclaredMethod'],
        'src/VarDumper/Dumper.php' => ['PhanTypeInvalidDimOffset'],
    ],
];
