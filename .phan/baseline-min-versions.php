<?php

/**
 * Baseline for issues that only appear with minimum dependency versions.
 *
 * These are false positives that occur when testing with:
 *   - nikic/php-parser: ^4.0 (oldest supported)
 *   - symfony/console: ^3.4 (oldest supported)
 *
 * The issues happen because Phan doesn't see newer APIs/classes that were added in later versions
 * (e.g., Node\Name::getParts() method, VariadicPlaceholder class, Int_/Float_ scalar nodes). The code
 * handles these version differences at runtime.
 *
 * WHEN TO REVIEW THIS FILE:
 *   - When dropping support for php-parser 4.x or symfony 3.x/4.x
 *   - Many of these suppressions can be removed once minimum versions increase
 */
return [
    'file_suppressions' => [
        'src/CodeCleaner.php' => ['PhanUndeclaredMethod'],
        'src/CodeCleaner/CallTimePassByReferencePass.php' => ['PhanUndeclaredClassInstanceof'],
        'src/CodeCleaner/CalledClassPass.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/FunctionReturnInWriteContextPass.php' => ['PhanUndeclaredClassInstanceof'],
        'src/CodeCleaner/ImplicitUsePass.php' => ['PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference', 'PhanUndeclaredMethod'],
        'src/CodeCleaner/IssetPass.php' => ['PhanUndeclaredClassInstanceof'],
        'src/CodeCleaner/ListPass.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassProperty'],
        'src/CodeCleaner/LoopContextPass.php' => ['PhanUndeclaredClassInstanceof'],
        'src/CodeCleaner/NamespaceAwarePass.php' => ['PhanUndeclaredMethod'],
        'src/CodeCleaner/NamespacePass.php' => ['PhanUndeclaredMethod'],
        'src/CodeCleaner/PassableByReferencePass.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/RequirePass.php' => ['PhanUndeclaredClassMethod'],
        'src/CodeCleaner/ReturnTypePass.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassProperty'],
        'src/CodeCleaner/StrictTypesPass.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassProperty'],
        'src/CodeCleaner/UseStatementPass.php' => ['PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference'],
        'src/CodeCleaner/ValidConstructorPass.php' => ['PhanUndeclaredMethod'],
        'src/Command/Command.php' => ['PhanUndeclaredMethod'],
        'src/Command/ShowCommand.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredMethod'],
        'src/Command/ThrowUpCommand.php' => ['PhanTypeMismatchArgument', 'PhanUndeclaredClassMethod'],
        'src/Configuration.php' => ['PhanTypeMismatchArgumentProbablyReal'],
        'src/Formatter/TraceFormatter.php' => ['PhanTypeMismatchArgumentNullable'],
        'src/Input/CodeArgument.php' => ['PhanTypeMismatchArgumentNullable'],
        'src/ParserFactory.php' => ['PhanUndeclaredMethod'],
        'src/VarDumper/Dumper.php' => ['PhanTypeInvalidDimOffset', 'PhanTypeMismatchArgument'],
        'src/functions.php' => ['PhanTypeMismatchArgumentProbablyReal'],
    ],
];
