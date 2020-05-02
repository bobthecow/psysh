<?php

/**
 * This baseline dynamically merges separate baseline files:
 *
 *  1. baseline-min-versions.php: false positives from minimum dependency versions
 *  2. baseline-new-deprecations.php: future deprecations from latest dependency versions
 *  3. baseline-internal-deprecations.php: internal PsySH BC deprecations
 *  4. baseline-external-deps.php: optional extensions and external dependencies
 */

require_once __DIR__ . '/merge-baselines.php';

return mergeBaselines([
    __DIR__ . '/baseline-min-versions.php',
    __DIR__ . '/baseline-new-deprecations.php',
    __DIR__ . '/baseline-internal-deprecations.php',
    __DIR__ . '/baseline-external-deps.php',
]);
