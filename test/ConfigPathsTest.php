<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
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

    /**
     * @dataProvider envVariablesForPathDirs
     */
    public function testPathDirs($pathEnv, $expectedPaths)
    {
        $paths = new ConfigPaths([], new TestableEnv(['PATH' => $pathEnv]));
        $this->assertEquals($expectedPaths, $paths->pathDirs());
    }

    public function envVariablesForPathDirs()
    {
        $base = \realpath(__DIR__.'/fixtures/which');

        return [
            [
                null,
                ['/usr/sbin', '/usr/bin', '/sbin', '/bin'],
            ],
            [
                "$base/usr/sbin:$base/usr/bin:$base/sbin:$base/home/foo/bin:$base/bin",
                [
                    "$base/usr/sbin",
                    "$base/usr/bin",
                    "$base/sbin",
                    "$base/home/foo/bin",
                    "$base/bin",
                ],
            ],
        ];
    }

    public function testWhich()
    {
        $base = \realpath(__DIR__.'/fixtures/which');

        $paths = new ConfigPaths([], new TestableEnv([
            'PATH' => "$base/home/username/bin:$base/usr/sbin:$base/usr/bin:$base/sbin:$base/bin",
        ]));

        $this->assertSame("$base/home/username/bin/foo", $paths->which('foo'));
        $this->assertSame("$base/usr/bin/bar", $paths->which('bar'));
        $this->assertNull($paths->which('baz'));

        $paths = new ConfigPaths([], new TestableEnv([
            'PATH' => "$base/usr/bin",
        ]));

        $this->assertSame("$base/usr/bin/foo", $paths->which('foo'));
        $this->assertSame("$base/usr/bin/bar", $paths->which('bar'));
        $this->assertNull($paths->which('baz'));

        // Fakebin has a bunch of directories named the same thing as the
        // commands we're looking for...
        $paths = new ConfigPaths([], new TestableEnv([
            'PATH' => "$base/fakebin:$base/usr/bin",
        ]));

        $this->assertSame("$base/usr/bin/foo", $paths->which('foo'));
        $this->assertSame("$base/usr/bin/bar", $paths->which('bar'));
        $this->assertNull($paths->which('baz'));

        // Notexec has a bunch of files without an executable bit named the same
        // thing as the commands we're looking for...
        $paths = new ConfigPaths([], new TestableEnv([
            'PATH' => "$base/notexec:$base/usr/bin",
        ]));

        $this->assertSame("$base/usr/bin/foo", $paths->which('foo'));
        $this->assertSame("$base/usr/bin/bar", $paths->which('bar'));
        $this->assertNull($paths->which('baz'));

        // Paths defined but missing commands return null
        $paths = new ConfigPaths([], new TestableEnv([
            'PATH' => "$base",
        ]));

        $this->assertNull($paths->which('foo'));
        $this->assertNull($paths->which('bar'));
        $this->assertNull($paths->which('baz'));
    }

    /**
     * @dataProvider prettyPathProvider
     */
    public function testPrettyPath($path, $expected, $relativeTo, $homeDir)
    {
        $result = ConfigPaths::prettyPath($path, $relativeTo, $homeDir);
        $this->assertSame($expected, $result);
    }

    public function prettyPathProvider()
    {
        return [
            // Non-string inputs
            'non-string: false' => [false, false, null, null],
            'non-string: null'  => [null, null, null, null],
            'non-string: int'   => [42, 42, null, null],

            // Unix/Linux/macOS: Current directory relative (relativeTo takes priority)
            'relative to cwd' => [
                '/home/user/project/foo.php',
                './foo.php',
                '/home/user/project',
                '/home/user',
            ],
            'relative to cwd nested' => [
                '/home/user/project/src/Bar.php',
                './src/Bar.php',
                '/home/user/project',
                '/home/user',
            ],
            'relative to cwd with trailing slash' => [
                '/home/user/project/foo.php',
                './foo.php',
                '/home/user/project/',
                '/home/user',
            ],

            // Unix/Linux/macOS: Home directory relative (when not in relativeTo)
            'home relative file' => [
                '/home/user/foo.php',
                '~/foo.php',
                '/tmp',
                '/home/user',
            ],
            'home relative nested' => [
                '/home/user/.config/psysh/config.php',
                '~/.config/psysh/config.php',
                '/tmp',
                '/home/user',
            ],
            'home with trailing slash' => [
                '/home/user/foo.php',
                '~/foo.php',
                '/tmp',
                '/home/user/',
            ],

            // Priority: relativeTo over home (both would match)
            'relativeTo priority over home' => [
                '/home/user/project/foo.php',
                './foo.php',
                '/home/user/project',
                '/home/user',
            ],

            // Global system paths (no replacement)
            'system path /usr' => [
                '/usr/local/share/psysh/manual.sqlite',
                '/usr/local/share/psysh/manual.sqlite',
                '/home/user/project',
                '/home/user',
            ],
            'system path /var' => [
                '/var/log/app.log',
                '/var/log/app.log',
                '/home/user',
                '/home/user',
            ],
            'system path /etc' => [
                '/etc/psysh/config.php',
                '/etc/psysh/config.php',
                '/home/user',
                '/home/user',
            ],

            // Edge cases
            'root home' => [
                '/root/.config/psysh/config.php',
                '~/.config/psysh/config.php',
                '/tmp',
                '/root',
            ],
            'home is root' => [
                '/foo.php',
                '/foo.php',
                '/tmp',
                '/',
            ],
            'relativeTo is home' => [
                '/home/user/foo.php',
                './foo.php',
                '/home/user',
                '/home/user',
            ],

            // Windows paths (forward slashes)
            'windows relative to cwd C drive' => [
                'C:/Users/user/project/foo.php',
                './foo.php',
                'C:/Users/user/project',
                'C:/Users/user',
            ],
            'windows home relative' => [
                'C:/Users/user/Documents/foo.php',
                '~/Documents/foo.php',
                'C:/temp',
                'C:/Users/user',
            ],
            'windows system path' => [
                'C:/Program Files/PHP/php.exe',
                'C:/Program Files/PHP/php.exe',
                'C:/Users/user/project',
                'C:/Users/user',
            ],
            'windows D drive not relative' => [
                'D:/data/file.php',
                'D:/data/file.php',
                'C:/Users/user/project',
                'C:/Users/user',
            ],

            // Windows paths (backslashes normalized to forward slashes)
            'windows backslash relative to cwd' => [
                'C:\\Users\\user\\project\\foo.php',
                './foo.php',
                'C:\\Users\\user\\project',
                'C:\\Users\\user',
            ],
            'windows backslash home relative' => [
                'C:\\Users\\user\\Documents\\foo.php',
                '~/Documents/foo.php',
                'C:\\temp',
                'C:\\Users\\user',
            ],
            'windows backslash system path' => [
                'C:\\Windows\\System32\\php.exe',
                'C:/Windows/System32/php.exe',
                'C:\\Users\\user\\project',
                'C:\\Users\\user',
            ],
            'windows backslash mixed with forward slash relativeTo' => [
                'C:\\Users\\user\\project\\foo.php',
                './foo.php',
                'C:/Users/user/project',
                'C:/Users/user',
            ],

            // Null parameters (use defaults from environment)
            'null relativeTo uses cwd' => [
                \getcwd().'/foo.php',
                './foo.php',
                null,
                '/home/user',
            ],
            'null homeDir uses actual home' => [
                (\getenv('HOME') ?: '/home/user').'/foo.php',
                '~/foo.php',
                '/tmp',
                null,
            ],
        ];
    }
}
