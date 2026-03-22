<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

final class TempPaths
{
    /** @var array<string, bool> */
    private static array $cleanupPaths = [];
    private static bool $cleanupRegistered = false;

    public static function file(string $prefix, ?string $dir = null): string
    {
        $path = \tempnam($dir ?? \sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new \RuntimeException(\sprintf('Failed to create temporary file for prefix: %s', $prefix));
        }

        self::registerCleanup($path);

        return $path;
    }

    public static function reserve(string $prefix, ?string $dir = null): string
    {
        $path = self::file($prefix, $dir);
        if (!@\unlink($path)) {
            throw new \RuntimeException(\sprintf('Failed to reserve temporary path for prefix: %s', $prefix));
        }

        return $path;
    }

    public static function directory(string $prefix, ?string $dir = null, int $mode = 0700): string
    {
        $path = self::reserve($prefix, $dir);
        if (!@\mkdir($path, $mode, true) && !@\is_dir($path)) {
            throw new \RuntimeException(\sprintf('Failed to create temporary directory for prefix: %s', $prefix));
        }

        return $path;
    }

    private static function registerCleanup(string $path): void
    {
        self::$cleanupPaths[$path] = true;

        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;

        \register_shutdown_function(static function (): void {
            foreach (\array_keys(self::$cleanupPaths) as $path) {
                self::rmrf($path);
            }
        });
    }

    private static function rmrf(string $path): void
    {
        if (!@\file_exists($path) && !@\is_link($path)) {
            return;
        }

        if (!@\is_dir($path) || @\is_link($path)) {
            @\unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @\rmdir($item->getPathname());
            } else {
                @\unlink($item->getPathname());
            }
        }

        @\rmdir($path);
    }
}
