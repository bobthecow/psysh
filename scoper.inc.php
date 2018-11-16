<?php

return [
    'whitelist' => [
        'Psy\*',
    ],

    'patchers' => [
        // Hoa patches
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/stream/Stream.php' !== $filePath) {
                return $contents;
            }

            return preg_replace(
                '/Hoa\\\\Consistency::registerShutdownFunction\(xcallable\(\'(.*)\'\)\)/',
                sprintf(
                    'Hoa\\Consistency::registerShutdownFunction(xcallable(\'%s$1\'))',
                    $prefix . '\\\\\\\\'
                ),
                $contents
            );
        },
        function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/hoa/consistency/Autoloader.php' !== $filePath) {
                return $contents;
            }
            $contents = preg_replace(
                '/(\$entityPrefix = \$entity;)/',
                sprintf(
                    '$entity = substr($entity, %d);$1',
                    strlen($prefix) + 1
                ),
                $contents
            );
            $contents = preg_replace(
                '/return \$this->runAutoloaderStack\((.*)\);/',
                sprintf(
                    'return $this->runAutoloaderStack(\'%s\'.\'%s\'.$1);',
                    $prefix,
                    '\\\\\\'
                ),
                $contents
            );

            return $contents;
        },
    ],
];
