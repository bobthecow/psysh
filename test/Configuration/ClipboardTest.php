<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Configuration;

use Psy\Clipboard\CommandClipboardMethod;
use Psy\Clipboard\NullClipboardMethod;
use Psy\Clipboard\Osc52ClipboardMethod;
use Psy\ConfigPaths;
use Psy\Configuration;
use Psy\Test\Fixtures\TestableEnv;
use Psy\Test\TestCase;

class ClipboardTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === false) {
                \putenv($name);
            } else {
                \putenv($name.'='.$value);
            }
        }
    }

    private function getConfig(): Configuration
    {
        return new Configuration([
            'configFile' => \dirname(__DIR__).'/Fixtures/empty.php',
        ]);
    }

    public function testGetClipboardUsesConfiguredCommand()
    {
        $config = $this->getConfig();
        $config->setClipboardCommand('totally-custom command --flag');

        $this->assertInstanceOf(CommandClipboardMethod::class, $config->getClipboard());
    }

    public function testGetClipboardUsesOsc52ForSshWhenEnabled()
    {
        $config = $this->getConfig();
        $config->setUseOsc52Clipboard(true);
        $this->setEnv('SSH_CONNECTION', '127.0.0.1 12345 127.0.0.1 22');

        $this->assertInstanceOf(Osc52ClipboardMethod::class, $config->getClipboard());
    }

    public function testGetClipboardUsesNullMethodForSshWhenDisabled()
    {
        $config = $this->getConfig();
        $this->setEnv('SSH_CLIENT', '127.0.0.1 12345 22');

        $this->assertInstanceOf(NullClipboardMethod::class, $config->getClipboard());
    }

    public function testGetClipboardDiscoversKnownCommandFromPath()
    {
        $config = $this->getConfig();
        $config->setClipboardCommand(null);
        $config->setUseOsc52Clipboard(false);

        $this->setConfigPaths($config, new ConfigPaths([], new TestableEnv([
            'PATH' => $this->makeKnownClipboardDir(),
        ])));

        $this->assertInstanceOf(CommandClipboardMethod::class, $config->getClipboard());
    }

    private function setEnv(string $name, string $value): void
    {
        if (!\array_key_exists($name, $this->savedEnv)) {
            $this->savedEnv[$name] = \getenv($name);
        }

        \putenv($name.'='.$value);
    }

    private function setConfigPaths(Configuration $config, ConfigPaths $configPaths): void
    {
        $reflection = new \ReflectionProperty(Configuration::class, 'configPaths');
        $reflection->setAccessible(true);
        $reflection->setValue($config, $configPaths);
    }

    private function makeKnownClipboardDir(): string
    {
        $dir = \sys_get_temp_dir().'/psysh-clipboard-'.\bin2hex(\random_bytes(8));
        $file = $dir.\DIRECTORY_SEPARATOR.(\DIRECTORY_SEPARATOR === '\\' ? 'clip.exe' : 'pbcopy');

        \mkdir($dir, 0777, true);
        \copy(\PHP_BINARY, $file);

        if (\DIRECTORY_SEPARATOR !== '\\') {
            \chmod($file, 0755);
        }

        \register_shutdown_function(static function () use ($file, $dir): void {
            @\unlink($file);
            @\rmdir($dir);
        });

        return $dir;
    }
}
