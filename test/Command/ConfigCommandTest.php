<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\ConfigCommand;
use Psy\Configuration;
use Psy\Shell;
use Psy\Test\Fixtures\Command\PsyCommandTester;
use Symfony\Component\Console\Output\StreamOutput;

class ConfigCommandTest extends \Psy\Test\TestCase
{
    private ConfigCommand $command;
    private Configuration $config;
    private Shell $shell;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir().'/psysh-config-command-test-'.\bin2hex(\random_bytes(8));
        \mkdir($this->tempDir, 0777, true);

        $this->config = new Configuration([
            'configFile'   => \dirname(__DIR__).'/Fixtures/empty.php',
            'configDir'    => $this->tempDir,
            'dataDir'      => $this->tempDir,
            'runtimeDir'   => $this->tempDir,
            'trustProject' => false,
            'colorMode'    => Configuration::COLOR_MODE_FORCED,
        ]);
        $this->shell = new Shell($this->config);

        $this->command = new ConfigCommand();
        $this->command->setConfiguration($this->config);
        $this->command->setApplication($this->shell);
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempDir.'/psysh_history');
        @\unlink($this->tempDir.'/psysh_history.jsonl');
        @\unlink($this->tempDir.'/update_check.json');
        @\unlink($this->tempDir.'/manual_update_check.json');
        @\rmdir($this->tempDir);
    }

    public function testConfigure()
    {
        $this->assertSame('config', $this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getHelp());
    }

    public function testListShowsOnlySupportedRuntimeKeys()
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute(['action' => 'list']);
        $display = $tester->getDisplay();

        foreach ([
            'verbosity',
            'useUnicode',
            'errorLoggingLevel',
            'clipboardCommand',
            'useOsc52Clipboard',
            'colorMode',
            'theme',
            'pager',
            'requireSemicolons',
            'useBracketedPaste',
            'useSuggestions',
        ] as $key) {
            $this->assertStringContainsString($key, $display);
        }

        $this->assertStringNotContainsString('prompt', $display);
        $this->assertStringNotContainsString('strictTypes', $display);
    }

    /**
     * @dataProvider getProvider
     */
    public function testGetShowsExpectedValues(string $key, string $expected)
    {
        $tester = new PsyCommandTester($this->command);
        $tester->execute([
            'action' => 'get',
            'key'    => $key,
        ]);

        $this->assertSame($expected.\PHP_EOL, $tester->getDisplay());
    }

    public function getProvider(): array
    {
        return [
            'verbosity' => ['verbosity', Configuration::VERBOSITY_NORMAL],
            'boolean'   => ['useUnicode', 'true'],
            'int'       => ['errorLoggingLevel', 'E_ALL'],
            'enum'      => ['colorMode', Configuration::COLOR_MODE_FORCED],
            'theme'     => ['theme', 'modern'],
        ];
    }

    /**
     * @dataProvider setProvider
     */
    public function testSetAcceptsSupportedValueShapes(string $key, string $value, string $expectedDisplay, callable $assertion): void
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(0, $tester->execute([
            'action' => 'set',
            'key'    => $key,
            'value'  => $value,
        ]));
        $this->assertStringContainsString($expectedDisplay, $tester->getDisplay());
        $assertion($this->config);
    }

    public function setProvider(): array
    {
        return [
            'bool' => [
                'useUnicode',
                'off',
                'useUnicode',
                function (Configuration $config): void {
                    $this->assertFalse($config->useUnicode());
                },
            ],
            'enum' => [
                'verbosity',
                'debug',
                'debug',
                function (Configuration $config): void {
                    $this->assertSame(Configuration::VERBOSITY_DEBUG, $config->verbosity());
                },
            ],
            'int' => [
                'errorLoggingLevel',
                (string) \E_WARNING,
                'E_WARNING',
                function (Configuration $config): void {
                    $this->assertSame((\PHP_VERSION_ID < 80400 ? (\E_ALL | \E_STRICT) : \E_ALL) & \E_WARNING, $config->errorLoggingLevel());
                },
            ],
            'string' => [
                'clipboardCommand',
                'pbcopy',
                'pbcopy',
                function (Configuration $config): void {
                    $this->assertSame('pbcopy', $config->clipboardCommand());
                },
            ],
            'pager off' => [
                'pager',
                'off',
                'off',
                function (Configuration $config): void {
                    $this->assertFalse($config->getPager());
                },
            ],
        ];
    }

    public function testSetPagerAcceptsBooleanAliasesForDefaultAndOff(): void
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(0, $tester->execute([
            'action' => 'set',
            'key'    => 'pager',
            'value'  => 'on',
        ]));
        $this->assertStringContainsString('pager', $tester->getDisplay());

        $this->assertSame(0, $tester->execute([
            'action' => 'set',
            'key'    => 'pager',
            'value'  => 'false',
        ]));
        $this->assertStringContainsString('off', $tester->getDisplay());
        $this->assertFalse($this->config->getPager());
    }

    public function testSetAcceptsConfigurationConstantsForEnumOptions(): void
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(0, $tester->execute([
            'action' => 'set',
            'key'    => 'colorMode',
            'value'  => 'Configuration::COLOR_MODE_FORCED',
        ]));
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $this->config->colorMode());

        $this->assertSame(0, $tester->execute([
            'action' => 'set',
            'key'    => 'verbosity',
            'value'  => 'Configuration::VERBOSITY_DEBUG',
        ]));
        $this->assertSame(Configuration::VERBOSITY_DEBUG, $this->config->verbosity());
    }

    public function testGetPrettyPrintsCommonErrorLoggingMasks(): void
    {
        $this->config->setErrorLoggingLevel(\E_ALL & ~\E_NOTICE);

        $tester = new PsyCommandTester($this->command);
        $tester->execute([
            'action' => 'get',
            'key'    => 'errorLoggingLevel',
        ]);

        $this->assertSame('E_ALL & ~E_NOTICE'.\PHP_EOL, $tester->getDisplay());
    }

    public function testGetThemeKeepsBuiltinThemeNameWhenLegacyPromptMatches(): void
    {
        $this->config->setTheme('classic');
        $this->config->setPrompt('>>> ');

        $tester = new PsyCommandTester($this->command);
        $tester->execute([
            'action' => 'get',
            'key'    => 'theme',
        ]);

        $this->assertSame('classic'.\PHP_EOL, $tester->getDisplay());
    }

    public function testInteractiveShellSetResolvesErrorLoggingLevelExpression(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $output = new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);
        $this->shell->setOutput($output);

        $method = new \ReflectionMethod(Shell::class, 'runCommand');
        $method->setAccessible(true);
        $method->invoke($this->shell, 'config set errorLoggingLevel (E_ALL & ~E_STRICT)');

        $expected = (\PHP_VERSION_ID < 80400 ? (\E_ALL | \E_STRICT) : \E_ALL) & (\E_ALL & ~\E_STRICT);

        $this->assertSame($expected, $this->config->errorLoggingLevel());

        \rewind($stream);
        $display = \stream_get_contents($stream);
        \fclose($stream);

        $this->assertStringContainsString('errorLoggingLevel', $display);
    }

    public function testInvalidValueFailsClearly()
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(1, $tester->execute([
            'action' => 'set',
            'key'    => 'useUnicode',
            'value'  => 'maybe',
        ]));
        $this->assertStringContainsString('Invalid useUnicode value: maybe. Accepted values: on|off', $tester->getDisplay());
    }

    public function testUnsupportedKeyFailsClearly()
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(1, $tester->execute([
            'action' => 'get',
            'key'    => 'strictTypes',
        ]));
        $this->assertStringContainsString('Configuration option `strictTypes` is not runtime-configurable.', $tester->getDisplay());
    }

    public function testDeprecatedKeysAreRejectedAsUnsupported()
    {
        $tester = new PsyCommandTester($this->command);

        $this->assertSame(1, $tester->execute([
            'action' => 'set',
            'key'    => 'prompt',
            'value'  => '>>> ',
        ]));
        $this->assertStringContainsString('Configuration option `prompt` is not runtime-configurable.', $tester->getDisplay());

        $this->assertSame(1, $tester->execute([
            'action' => 'set',
            'key'    => 'formatterStyles',
            'value'  => 'foo',
        ]));
        $this->assertStringContainsString('Configuration option `formatterStyles` is not runtime-configurable.', $tester->getDisplay());
    }

    public function testInteractiveShellSetAcceptsCodeArgumentValue(): void
    {
        $stream = \fopen('php://memory', 'w+');
        $output = new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);
        $this->shell->setOutput($output);

        $method = new \ReflectionMethod(Shell::class, 'runCommand');
        $method->setAccessible(true);
        $method->invoke($this->shell, 'config set clipboardCommand pbcopy --flag --verbose');

        $this->assertSame('pbcopy --flag --verbose', $this->config->clipboardCommand());

        \fclose($stream);
    }
}
