<?php

/**
 * Helper function to merge multiple Phan baseline files.
 *
 * @param string[] $baselineFiles Array of baseline file paths to merge
 *
 * @return array Merged baseline array with 'file_suppressions' key
 */
function mergeBaselines(array $baselineFiles): array
{
    $merged = ['file_suppressions' => []];

    foreach ($baselineFiles as $baseline) {
        if (file_exists($baseline)) {
            $data = require $baseline;
            if (isset($data['file_suppressions'])) {
                foreach ($data['file_suppressions'] as $file => $suppressions) {
                    if (!isset($merged['file_suppressions'][$file])) {
                        $merged['file_suppressions'][$file] = [];
                    }
                    $merged['file_suppressions'][$file] = array_values(array_unique(
                        array_merge($merged['file_suppressions'][$file], $suppressions)
                    ));
                }
            }
        }
    }

    return $merged;
}
