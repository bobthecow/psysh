<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 */
return [

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'src/',
        'vendor/nikic/php-parser/lib/',
        'vendor/symfony/console/',
        'vendor/symfony/var-dumper/',
        'vendor/symfony/polyfill-ctype/',
        'vendor/symfony/polyfill-intl-grapheme/',
        'vendor/symfony/polyfill-intl-normalizer/',
        'vendor/symfony/polyfill-mbstring/',
        'vendor/symfony/polyfill-php73/',
        'vendor/symfony/polyfill-php80/',
    ],

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to both the `directory_list`
    //       and `exclude_analysis_directory_list` arrays.
    "exclude_analysis_directory_list" => [
        'vendor/',
        'src/Readline/Hoa/',
    ],

    // The baseline.php merges separate baseline files for clarity:
    //   - baseline-min-versions.php: False positives from minimum versions
    //   - baseline-new-deprecations.php: Future deprecations from latest versions
    //   - baseline-internal-deprecations.php: Internal PsySH BC deprecations
    'baseline_path' => __DIR__ . '/baseline.php',
];
