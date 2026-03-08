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

use Psy\Configuration;
use Psy\Output\PassthruPager;
use Psy\Output\ProcOutputPager;
use Psy\Output\ShellOutput;
use Psy\Readline\Interactive\Input\History;
use Psy\Readline\InteractiveReadlineInterface;
use Psy\Shell;
use Symfony\Component\Console\Output\StreamOutput;

class ShellRuntimeConfigTest extends TestCase
{
    private array $streams = [];
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            \fclose($stream);
        }

        foreach ($this->tempDirs as $dir) {
            @\unlink($dir.'/psysh_history');
            @\unlink($dir.'/psysh_history.jsonl');
            @\unlink($dir.'/update_check.json');
            @\unlink($dir.'/manual_update_check.json');
            @\rmdir($dir);
        }
    }

    public function testApplyRuntimeConfigChangeUpdatesOutputStateImmediately()
    {
        $config = $this->getConfig([
            'colorMode' => Configuration::COLOR_MODE_FORCED,
        ]);
        $shell = new Shell($config);
        $output = $config->getOutput();
        $shell->setOutput($output);

        $config->setVerbosity(Configuration::VERBOSITY_DEBUG);
        $shell->applyRuntimeConfigChange('verbosity');
        $this->assertSame(StreamOutput::VERBOSITY_DEBUG, $output->getVerbosity());

        $config->setColorMode(Configuration::COLOR_MODE_DISABLED);
        $shell->applyRuntimeConfigChange('colorMode');
        $this->assertFalse($output->isDecorated());

        $config->setTheme('compact');
        $shell->applyRuntimeConfigChange('theme');
        $this->assertTrue($this->getOutputTheme($output)->compact());
    }

    public function testApplyRuntimeConfigChangePersistsVerbosityForNextPrompt()
    {
        $config = $this->getConfig();
        $shell = new Shell($config);
        $output = $this->getOutput();

        $readline = $this->getMockBuilder(InteractiveReadlineInterface::class)->getMock();
        $readline->expects($this->once())->method('setOutput');
        $readline->method('setTheme');
        $readline->method('setRequireSemicolons');
        $readline->method('setBracketedPaste');
        $readline->method('setUseSuggestions');
        $readline->method('getHistory')->willReturn(new History());
        $readline->method('readHistory')->willReturn(true);
        $readline->method('writeHistory')->willReturn(true);
        $readline->method('addHistory')->willReturn(true);
        $readline->method('clearHistory')->willReturn(true);
        $readline->method('listHistory')->willReturn([]);
        $readline->method('readline')->willReturn(false);
        $readline->method('redisplay');
        $readline->method('setCompletionEngine');
        $readline->method('setOutputWritten');

        $config->setReadline($readline);
        $shell->setOutput($output);
        $shell->boot(null, $output);

        $config->setVerbosity(Configuration::VERBOSITY_DEBUG);
        $shell->applyRuntimeConfigChange('verbosity');
        $this->assertSame(StreamOutput::VERBOSITY_DEBUG, $output->getVerbosity());

        try {
            $shell->getInput();
            $this->fail('Expected Ctrl+D to break out of the input loop.');
        } catch (\Psy\Exception\BreakException $e) {
            $this->assertStringContainsString('Ctrl+D', $e->getMessage());
        }

        $this->assertSame(StreamOutput::VERBOSITY_DEBUG, $output->getVerbosity());
    }

    public function testApplyRuntimeConfigChangeSwapsPagerImmediately()
    {
        $config = $this->getConfig([
            'pager' => 'less -R -F -X',
        ]);
        $shell = new Shell($config);
        $output = $config->getOutput();
        $shell->setOutput($output);

        $this->assertInstanceOf(ProcOutputPager::class, $this->getOutputPager($output));

        $config->setPager(false);
        $shell->applyRuntimeConfigChange('pager');
        $this->assertInstanceOf(PassthruPager::class, $this->getOutputPager($output));
    }

    public function testApplyRuntimeConfigChangeRefreshesInteractiveReadline()
    {
        $config = $this->getConfig();
        $shell = new Shell($config);
        $output = $this->getOutput();

        $readline = $this->getMockBuilder(InteractiveReadlineInterface::class)->getMock();
        $readline->expects($this->once())->method('setOutput');
        $readline->expects($this->exactly(2))->method('setTheme');
        $readline->expects($this->exactly(2))->method('setRequireSemicolons');
        $readline->expects($this->exactly(2))->method('setBracketedPaste');
        $readline->expects($this->exactly(2))->method('setUseSuggestions');
        $readline->method('getHistory')->willReturn(new History());
        $readline->method('readHistory')->willReturn(true);
        $readline->method('writeHistory')->willReturn(true);
        $readline->method('addHistory')->willReturn(true);
        $readline->method('clearHistory')->willReturn(true);
        $readline->method('listHistory')->willReturn([]);
        $readline->method('readline')->willReturn(false);
        $readline->method('redisplay');
        $readline->method('setCompletionEngine');
        $readline->method('setOutputWritten');

        $config->setReadline($readline);
        $shell->boot(null, $output);

        $config->setTheme('compact');
        $shell->applyRuntimeConfigChange('theme');
        $config->setRequireSemicolons(true);
        $shell->applyRuntimeConfigChange('requireSemicolons');
        $config->setUseBracketedPaste(true);
        $shell->applyRuntimeConfigChange('useBracketedPaste');
        $config->setUseSuggestions(true);
        $shell->applyRuntimeConfigChange('useSuggestions');
    }

    public function testUseUnicodeIsReadFromConfigWithoutRestart()
    {
        $config = $this->getConfig([
            'useUnicode' => true,
        ]);
        $shell = new Shell($config);

        $method = new \ReflectionMethod(Shell::class, 'getHeader');
        $method->setAccessible(true);
        $this->assertStringContainsString(' — ', $method->invoke($shell));

        $config->setUseUnicode(false);
        $this->assertStringContainsString(' - ', $method->invoke($shell));
    }

    private function getConfig(array $config = []): Configuration
    {
        $dir = \tempnam(\sys_get_temp_dir(), 'psysh_runtime_config_test_');
        \unlink($dir);
        $this->tempDirs[] = $dir;

        $defaults = [
            'configDir'    => $dir,
            'dataDir'      => $dir,
            'runtimeDir'   => $dir,
            'configFile'   => __DIR__.'/Fixtures/empty.php',
            'trustProject' => false,
        ];

        return new Configuration(\array_merge($defaults, $config));
    }

    private function getOutput(): StreamOutput
    {
        $stream = \fopen('php://memory', 'w+');
        $this->streams[] = $stream;

        return new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);
    }

    private function getOutputPager(ShellOutput $output)
    {
        $reflection = new \ReflectionProperty(ShellOutput::class, 'pager');
        $reflection->setAccessible(true);

        return $reflection->getValue($output);
    }

    private function getOutputTheme(ShellOutput $output)
    {
        $reflection = new \ReflectionProperty(ShellOutput::class, 'theme');
        $reflection->setAccessible(true);

        return $reflection->getValue($output);
    }
}
