<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\ConfigPaths;

class ConfigPathsTest extends TestCase
{
    public function testOverrideDirs()
    {
        $paths = new ConfigPaths([
            'configDir'  => 'foo',
            'dataDir'    => 'bar',
            'runtimeDir' => 'baz',
        ], new TestableEnv());

        $this->assertSame(['foo'], $paths->configDirs());
        $this->assertSame(['bar'], $paths->dataDirs());
        $this->assertSame('baz', $paths->runtimeDir());

        $paths->overrideDirs([
            'configDir' => 'qux',
        ]);

        $this->assertSame(['qux'], $paths->configDirs());
        $this->assertSame(['bar'], $paths->dataDirs());
        $this->assertSame('baz', $paths->runtimeDir());

        $paths->overrideDirs([
            'configDir'  => 'a',
            'dataDir'    => 'b',
            'runtimeDir' => null,
        ]);

        $this->assertSame(['a'], $paths->configDirs());
        $this->assertSame(['b'], $paths->dataDirs());
        $this->assertSame(\sys_get_temp_dir().'/psysh', $paths->runtimeDir());
    }

    /**
     * @dataProvider envVariablesAndPathOverrides
     */
    public function testEnvVariables($env, $overrides, $configDir, $dataDirs, $runtimeDir)
    {
        $paths = new ConfigPaths($overrides, new TestableEnv($env));

        $this->assertSame(\realpath($configDir), \realpath($paths->currentConfigDir()));
        $this->assertEquals(\array_map('realpath', $dataDirs), \array_values(\array_filter(\array_map('realpath', $paths->dataDirs()))));
        $this->assertSame(\realpath($runtimeDir), \realpath($paths->runtimeDir()));
    }

    public function envVariablesAndPathOverrides()
    {
        $base = \realpath(__DIR__.'/fixtures');

        return [
            // Mimic actual config directory structure, with no XDG config set.
            [
                [
                    'HOME' => $base.'/default',
                ],
                [],
                $base.'/default/.config/psysh',
                [
                    $base.'/default/.local/share/psysh',
                ],
                \sys_get_temp_dir().'/psysh',
            ],
            [
                [
                    'HOME' => $base.'/legacy',
                ],
                [],
                $base.'/legacy/.psysh',
                [
                    $base.'/legacy/.psysh',
                ],
                \sys_get_temp_dir().'/psysh',
            ],
            [
                [
                    'HOME' => $base.'/mixed',
                ],
                [],
                $base.'/mixed/.psysh',
                [
                    $base.'/mixed/.psysh',
                ],
                \sys_get_temp_dir().'/psysh',
            ],

            // Same as before but with XDG stuffs!
            [
                [
                    'HOME'            => $base.'/default',
                    'XDG_CONFIG_HOME' => $base.'/xdg/config',
                    'XDG_DATA_HOME'   => $base.'/xdg/data',
                    'XDG_RUNTIME_DIR' => $base.'/xdg/runtime',
                ],
                [],
                $base.'/xdg/config/psysh',
                [
                    $base.'/xdg/data/psysh',
                ],
                $base.'/xdg/runtime/psysh',
            ],
            [
                [
                    'HOME'            => $base.'/default',
                    'XDG_CONFIG_HOME' => $base.'/xdg/config',
                    'XDG_DATA_HOME'   => $base.'/xdg/data',
                    'XDG_RUNTIME_DIR' => $base.'/xdg/runtime',
                ],
                [
                    'configDir'  => $base.'/default/.config/psysh',
                    'dataDir'    => $base.'/legacy/.psysh',
                    'runtimeDir' => $base.'/legacy/.psysh',
                ],
                $base.'/default/.config/psysh',
                [
                    $base.'/legacy/.psysh',
                ],
                $base.'/legacy/.psysh',
            ],
        ];
    }
}
