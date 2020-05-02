<?php

/**
 * Phan baseline for issues related to external dependencies and optional extensions.
 *
 * This includes:
 * - PHP Parser node properties (intentionally dynamic)
 * - Optional PHP extensions (Runkit, MongoDB)
 * - Optional Composer classes (ClassMapGenerator, ClassLoader)
 * - Optional PSR interfaces (LoggerInterface)
 * - PHP 8.x reflection classes for backward compatibility
 */
return [
    'file_suppressions' => [
        'src/CodeCleaner/AssignThisVariablePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/FunctionReturnInWriteContextPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/ListPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/LoopContextPass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/RequirePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/ReturnTypePass.php' => ['PhanUndeclaredProperty'],
        'src/CodeCleaner/ValidClassNamePass.php' => ['PhanUndeclaredProperty'],
        'src/Configuration.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredTypeParameter'],
        'src/ExecutionLoop/ProcessForker.php' => ['PhanUndeclaredClassInstanceof'],
        'src/ExecutionLoop/RunkitReloader.php' => ['PhanUndeclaredConstant'],
        'src/Formatter/SignatureFormatter.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
        'src/ShellLogger.php' => ['PhanUndeclaredClassMethod', 'PhanUndeclaredTypeParameter'],
        'src/Sudo/SudoVisitor.php' => ['PhanUndeclaredProperty'],
        'src/TabCompletion/AutoloadWarmer/ComposerAutoloadWarmer.php' => ['PhanUndeclaredClassMethod'],
        'src/TabCompletion/Matcher/MongoClientMatcher.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
        'src/TabCompletion/Matcher/MongoDatabaseMatcher.php' => ['PhanUndeclaredClassInstanceof', 'PhanUndeclaredClassMethod'],
    ],
];
