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
    /**
     * Create and apply a hermetic test environment for PsySH config/data.
     *
     * @return array<string, string> Applied directory paths keyed by env name
     */
    public static function isolate(?string $root = null): array
    {
        $root = $root ?? TempPaths::directory('psysh-test-env-', null, 0777);

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

        return [
            'HOME'            => $homeDir,
            'XDG_CONFIG_HOME' => $configHome,
            'XDG_DATA_HOME'   => $dataHome,
            'XDG_RUNTIME_DIR' => $runtimeDir,
            'XDG_CONFIG_DIRS' => $configDirs,
            'XDG_DATA_DIRS'   => $dataDirs,
        ];
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
}
