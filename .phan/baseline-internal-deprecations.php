<?php

/**
 * Baseline for internal PsySH deprecations.
 *
 * These are deprecations in PsySH's own code that are maintained for backward compatibility until
 * the next 0.x.0 minor version release.
 *
 * WHEN TO REVIEW THIS FILE:
 *   - Before the next 0.x.0 minor version release
 *   - When removing deprecated code
 *   - After removal, delete this baseline file
 */
return [
    'file_suppressions' => [
        'src/Configuration.php' => ['PhanDeprecatedFunction', 'PhanDeprecatedProperty'],
        'src/Shell.php' => ['PhanDeprecatedFunction'],
    ],
];
