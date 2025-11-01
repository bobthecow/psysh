<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with --save-baseline=path/to/baseline.php
 * (can be combined with --load-baseline)
 */
return [
    // # Issue statistics:
    // PhanUndeclaredProperty : 35+ occurrences
    // PhanDeprecatedProperty : 10+ occurrences
    // PhanTypeArraySuspiciousNullable : 10+ occurrences
    // PhanUndeclaredClassMethod : 10+ occurrences
    // PhanUndeclaredMethod : 10+ occurrences
    // PhanTypeMismatchArgument : 8 occurrences
    // PhanUndeclaredConstant : 8 occurrences
    // PhanUndeclaredClassInstanceof : 6 occurrences
    // PhanDeprecatedClass : 5 occurrences
    // PhanDeprecatedFunction : 5 occurrences
    // PhanTypeMismatchReturnNullable : 5 occurrences
    // PhanTypeMismatchArgumentSuperType : 4 occurrences
    // PhanTypeMismatchArgumentInternal : 3 occurrences
    // PhanTypeMismatchArgumentNullable : 3 occurrences
    // PhanUndeclaredTypeParameter : 3 occurrences
    // PhanTypeMismatchArgumentNullableInternal : 2 occurrences
    // PhanTypeMismatchArgumentProbablyReal : 2 occurrences
    // PhanUndeclaredClassReference : 2 occurrences
    // PhanTypeNonVarPassByRef : 1 occurrence
    // PhanUndeclaredConstantOfClass : 1 occurrence

    // Currently, file_suppressions and directory_suppressions are the only supported suppressions
    'file_suppressions' => [
        'src/CodeCleaner.php' => ['PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchReturnNullable', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/AssignThisVariablePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/CalledClassPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/FunctionReturnInWriteContextPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/ImplicitUsePass.php' => ['PhanDeprecatedClass', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/ListPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/LoopContextPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/NamespaceAwarePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/NamespacePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/PassableByReferencePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/RequirePass.php' => ['PhanDeprecatedClass', 'PhanTypeMismatchArgumentProbablyReal', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/ReturnTypePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/StrictTypesPass.php' => ['PhanDeprecatedClass'],
        'src/CodeCleaner/UseStatementPass.php' => ['PhanDeprecatedClass', 'PhanUndeclaredClassMethod', 'PhanUndeclaredClassReference'],
        'src/CodeCleaner/ValidClassNamePass.php' => ['PhanTypeMismatchArgument', 'PhanUndeclaredProperty'],
        'src/CodeCleaner/ValidConstructorPass.php' => ['PhanUndeclaredMethod', 'PhanUndeclaredProperty'],
        'src/Command/CodeArgumentParser.php' => ['PhanTypeMismatchReturnNullable'],
        'src/Command/Command.php' => ['PhanUndeclaredMethod'],
        'src/Command/HelpCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/HistoryCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/ParseCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/ShowCommand.php' => ['PhanUndeclaredMethod'],
        'src/Command/TraceCommand.php' => ['PhanUndeclaredMethod'],
        'src/ConfigPaths.php' => ['PhanTypeMismatchArgument'],
        'src/Configuration.php' => ['PhanDeprecatedFunction', 'PhanDeprecatedProperty', 'PhanTypeMismatchArgument', 'PhanTypeMismatchArgumentSuperType', 'PhanUndeclaredClassInstanceof', 'PhanUndeclaredTypeParameter'],
        'src/ExecutionLoop/ProcessForker.php' => ['PhanUndeclaredClassInstanceof'],
        'src/ExecutionLoop/RunkitReloader.php' => ['PhanUndeclaredConstant'],
        'src/Formatter/CodeFormatter.php' => ['PhanTypeMismatchArgumentSuperType'],
        'src/Formatter/SignatureFormatter.php' => ['PhanTypeMismatchArgumentProbablyReal', 'PhanTypeMismatchArgumentSuperType', 'PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
        'src/Formatter/TraceFormatter.php' => ['PhanTypeMismatchArgumentNullable', 'PhanTypeMismatchArgumentNullableInternal'],
        'src/ManualUpdater/GitHubChecker.php' => ['PhanTypeMismatchArgumentInternal'],
        'src/ParserFactory.php' => ['PhanUndeclaredConstantOfClass', 'PhanUndeclaredMethod'],
        'src/Shell.php' => ['PhanDeprecatedFunction', 'PhanTypeMismatchArgument', 'PhanTypeMismatchArgumentSuperType', 'PhanTypeMismatchReturnNullable', 'PhanTypeNonVarPassByRef'],
        'src/ShellLogger.php' => ['PhanUndeclaredClassMethod', 'PhanUndeclaredTypeParameter'],
        'src/Sudo/SudoVisitor.php' => ['PhanUndeclaredProperty'],
        'src/TabCompletion/AutoloadWarmer/ComposerAutoloadWarmer.php' => ['PhanUndeclaredClassMethod'],
        'src/TabCompletion/Matcher/AbstractMatcher.php' => ['PhanTypeArraySuspiciousNullable'],
        'src/TabCompletion/Matcher/ClassMethodDefaultParametersMatcher.php' => ['PhanTypeArraySuspiciousNullable'],
        'src/TabCompletion/Matcher/CommandsMatcher.php' => ['PhanTypeArraySuspiciousNullable'],
        'src/TabCompletion/Matcher/FunctionDefaultParametersMatcher.php' => ['PhanTypeArraySuspiciousNullable'],
        'src/TabCompletion/Matcher/MongoClientMatcher.php' => ['PhanTypeArraySuspiciousNullable', 'PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
        'src/TabCompletion/Matcher/MongoDatabaseMatcher.php' => ['PhanTypeArraySuspiciousNullable', 'PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
        'src/TabCompletion/Matcher/ObjectMethodDefaultParametersMatcher.php' => ['PhanTypeArraySuspiciousNullable'],
        'src/VersionUpdater/GitHubChecker.php' => ['PhanTypeMismatchArgumentInternal', 'PhanTypeMismatchArgumentNullable'],
    ],
    // 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
    // (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
