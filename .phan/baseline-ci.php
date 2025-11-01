<?php

/**
 * This baseline merges the canonical baseline with current issues for CI enforcement:
 *
 *  1. baseline.php: all canonical baselines (min-versions, new-deprecations, internal-deprecations, external-deps)
 *  2. baseline-current-issues.php: existing issues we're holding the line on (auto-generated)
 *
 * This allows CI to enforce "no new issues" while `make phan` locally shows all issues.
 *
 * To update the current issues baseline:
 *   vendor/bin/phan --allow-polyfill-parser --save-baseline=.phan/baseline-current-issues.php
 */

require_once __DIR__ . '/merge-baselines.php';

return mergeBaselines([
    __DIR__ . '/baseline.php',
    __DIR__ . '/baseline-current-issues.php',
]);
