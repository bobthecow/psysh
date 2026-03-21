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

final class BootstrapEnv
{
    /** @var array<string, bool> */
    private static array $cleanupRoots = [];

    /**
     * Create and apply a hermetic test environment for PsySH config/data.
     *
     * @return array<string, string> Applied directory paths keyed by env name
     */
    public static function isolate(?string $root = null): array
    {
        $root = $root ?? self::makeTempDir('psysh-test-env-');

        $homeDir = $root.'/home';
        $configHome = $homeDir.'/.config';
        $dataHome = $homeDir.'/.local/share';
        $runtimeDir = $root.'/runtime';
        $configDirs = $root.'/config-dirs';
        $dataDirs = $root.'/data-dirs';

        foreach ([
            $homeDir,
            $configHome.'/psysh',
            $dataHome.'/psysh',
            $runtimeDir,
            $configDirs,
            $dataDirs,
        ] as $dir) {
            self::mkdir($dir);
        }

        self::setEnv('HOME', $homeDir);
        self::setEnv('XDG_CONFIG_HOME', $configHome);
        self::setEnv('XDG_DATA_HOME', $dataHome);
        self::setEnv('XDG_RUNTIME_DIR', $runtimeDir);
        self::setEnv('XDG_CONFIG_DIRS', $configDirs);
        self::setEnv('XDG_DATA_DIRS', $dataDirs);

        self::unsetEnv('PSYSH_CONFIG');
        self::unsetEnv('PSYSH_TRUST_PROJECT');
        self::unsetEnv('PSYSH_UNTRUSTED_PROJECT');
        self::registerCleanup($root);

        return [
            'HOME'            => $homeDir,
            'XDG_CONFIG_HOME' => $configHome,
            'XDG_DATA_HOME'   => $dataHome,
            'XDG_RUNTIME_DIR' => $runtimeDir,
            'XDG_CONFIG_DIRS' => $configDirs,
            'XDG_DATA_DIRS'   => $dataDirs,
        ];
    }

    private static function makeTempDir(string $prefix): string
    {
        $path = \tempnam(\sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new \RuntimeException('Failed to allocate temporary test directory');
        }

        @\unlink($path);
        self::mkdir($path);

        return $path;
    }

    private static function mkdir(string $dir): void
    {
        if (!@\mkdir($dir, 0777, true) && !@\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Failed to create test directory: %s', $dir));
        }
    }

    private static function setEnv(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        \putenv($name.'='.$value);
    }

    private static function unsetEnv(string $name): void
    {
        unset($_SERVER[$name], $_ENV[$name]);
        \putenv($name);
    }

    private static function registerCleanup(string $root): void
    {
        if (isset(self::$cleanupRoots[$root])) {
            return;
        }

        self::$cleanupRoots[$root] = true;

        \register_shutdown_function(static function () use ($root): void {
            self::rmrf($root);
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
