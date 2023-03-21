<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Finder\Finder;

$polyfillsBootstraps = \array_map(
    function (SplFileInfo $fileInfo) {
        return $fileInfo->getPathname();
    },
    \iterator_to_array(
        Finder::create()
            ->files()
            ->in(__DIR__.'/vendor/symfony/polyfill-*')
            ->name('bootstrap*.php'),
        false
    )
);

$polyfillsStubs = [];

try {
    $polyfillsStubs = \array_map(
        function (SplFileInfo $fileInfo) {
            return $fileInfo->getPathname();
        },
        \iterator_to_array(
            Finder::create()
                ->files()
                ->in(__DIR__.'/vendor/symfony/polyfill-*/Resources/stubs')
                ->name('*.php'),
            false
        )
    );
} catch (Throwable $e) {
    // There may not be any stubs?
}

return [
    'exclude-namespaces' => [
        'Psy',
        'Symfony\Polyfill',
    ],

    'exclude-constants' => [
        // Symfony global constants
        '/^SYMFONY\_[\p{L}_]+$/',
    ],

    'exclude-files' => \array_merge($polyfillsBootstraps, $polyfillsStubs),

    'patchers' => [
        // https://github.com/humbug/php-scoper/issues/294
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if (!\in_array($filePath, ['src/Formatter/DocblockFormatter.php', 'src/Output/ShellOutput.php'], true)) {
                return $contents;
            }

            return \str_replace(
                '\'Symfony\\\\Component\\\\Console\\\\Formatter\\\\OutputFormatter\'',
                \sprintf(
                    '\'%s\\%s\'',
                    $prefix,
                    'Symfony\\Component\\Console\\Formatter\\OutputFormatter'
                ),
                $contents
            );
        },
        // Symfony patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/debug/DebugClassLoader.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/case \'(Symfony\\\\.+\\\\)\':/',
                \sprintf(
                    'case \'%s\\\\\\\$1\':',
                    $prefix
                ),
                $contents
            );
        },
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/var-dumper/Cloner/AbstractCloner.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/\'(Symfony\\\\.+?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );
        },
        // https://github.com/humbug/php-scoper/issues/286
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/symfony/debug/Exception/FlattenException.php' !== $filePath) {
                return $contents;
            }

            return \preg_replace(
                '/\'(Symfony\\\\.+?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );
        },
        // PHP-Parser patches
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/JsonDecoder.php' !== $filePath) {
                return $contents;
            }

            return \str_replace(
                '\'PhpParser\\\\Node\\\\\'',
                \sprintf(
                    '\'%s\\\\PhpParser\\\\Node\\\\\'',
                    $prefix
                ),
                $contents
            );
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/Unserializer/XML.php' !== $filePath) {
                return $contents;
            }

            $contents = \preg_replace(
                '/\'(PhpParser\\\\.+(?:\\\\)?)\'/',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );

            $contents = \preg_replace(
                '/\'(PhpParser\\\\\\\\\p{L}+)(?:\\\\\\\\)?\'/u',
                \sprintf(
                    '\'%s\\\\\\\$1\'',
                    $prefix
                ),
                $contents
            );

            return $contents;
        },
        static function (string $filePath, string $prefix, string $contents): string {
            if ('vendor/nikic/php-parser/lib/PhpParser/Lexer.php' !== $filePath) {
                return $contents;
            }

            return \str_replace(
                '\'PhpParser\\\\Parser\\\\Tokens::\'',
                \sprintf(
                    '\'%s\\\\PhpParser\\\\Parser\\\\Tokens::\'',
                    $prefix
                ),
                $contents
            );
        },
    ],
];
