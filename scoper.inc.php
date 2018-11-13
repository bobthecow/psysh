<?php

return [
    'whitelist' => [
        'Psy\Shell',
        'Psy\bin',
    ],
    'patchers' => [
        function (string $filePath, string $prefix, string $contents): string {
            return 'src/ConfigPaths.php' === $filePath
                ? str_replace(
                    "'Psy\\\\Exception\\\\ErrorException'",
                    "'$prefix\\\\Psy\\\\Exception\\\\ErrorException'",
                    $contents
                )
                : $contents
            ;
        },
    ],
];
