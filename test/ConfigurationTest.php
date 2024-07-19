<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;
use Psy\Configuration;
use Psy\ExecutionLoop\ProcessForker;
use Psy\Output\PassthruPager;
use Psy\Output\ShellOutput;
use Psy\VersionUpdater\GitHubChecker;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigurationTest extends TestCase
{
    private function getConfig($configFile = null)
    {
        return new Configuration([
            'configFile' => $configFile ?: __DIR__.'/fixtures/empty.php',
        ]);
    }

    public function testDefaults()
    {
        $config = $this->getConfig();

        $this->assertSame(\function_exists('readline'), $config->hasReadline());
        $this->assertSame(\function_exists('readline'), $config->useReadline());
        $this->assertSame(ProcessForker::isSupported(), $config->hasPcntl());
        $this->assertSame($config->hasPcntl(), $config->usePcntl());
        $this->assertFalse($config->requireSemicolons());
        $this->assertSame(Configuration::COLOR_MODE_AUTO, $config->colorMode());
        $this->assertNull($config->getStartupMessage());
    }

    public function testGettersAndSetters()
    {
        $config = $this->getConfig();

        $this->assertNull($config->getDataDir());
        $config->setDataDir('wheee');
        $this->assertSame('wheee', $config->getDataDir());

        $this->assertNull($config->getConfigDir());
        $config->setConfigDir('wheee');
        $this->assertSame('wheee', $config->getConfigDir());
    }

    public function testGetRuntimeDir()
    {
        $dirName = \tempnam(\sys_get_temp_dir(), 'psysh-config-test-');
        \unlink($dirName);

        $config = $this->getConfig();
        $config->setRuntimeDir($dirName);

        $this->assertSame($config->getRuntimeDir(false), $dirName);
        $this->assertDirectoryDoesNotExist($dirName);

        $this->assertSame($config->getRuntimeDir(true), $dirName);
        $this->assertDirectoryExists($dirName);
    }

    /**
     * @group isolation-fail
     */
    public function testLoadConfig()
    {
        $config = $this->getConfig();
        $cleaner = new CodeCleaner();
        $pager = new PassthruPager(new ConsoleOutput());

        $config->loadConfig([
            'useReadline'       => false,
            'usePcntl'          => false,
            'codeCleaner'       => $cleaner,
            'pager'             => $pager,
            'requireSemicolons' => true,
            'errorLoggingLevel' => \E_ERROR | \E_WARNING,
            'colorMode'         => Configuration::COLOR_MODE_FORCED,
            'startupMessage'    => 'Psysh is awesome!',
        ]);

        $this->assertFalse($config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame($cleaner, $config->getCodeCleaner());
        $this->assertSame($pager, $config->getPager());
        $this->assertTrue($config->requireSemicolons());
        $this->assertSame(\E_ERROR | \E_WARNING, $config->errorLoggingLevel());
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode());
        $this->assertSame('Psysh is awesome!', $config->getStartupMessage());
    }

    public function testLoadConfigFile()
    {
        $config = $this->getConfig(__DIR__.'/fixtures/config.php');

        $runtimeDir = $this->joinPath(\realpath(\sys_get_temp_dir()), 'psysh_test', 'withconfig', 'temp');

        $this->assertStringStartsWith($runtimeDir, \realpath($config->getTempFile('foo', 123)));
        $this->assertStringStartsWith($runtimeDir, \realpath(\dirname($config->getPipe('pipe', 123))));
        $this->assertStringStartsWith($runtimeDir, \realpath($config->getRuntimeDir()));

        $this->assertSame(\function_exists('readline'), $config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame(\E_ALL & ~\E_NOTICE, $config->errorLoggingLevel());
    }

    public function testLoadLocalConfigFile()
    {
        $oldPwd = \getcwd();
        \chdir(\realpath(__DIR__.'/fixtures/project/'));

        $config = new Configuration();

        // When no configuration file is specified local project config is merged
        $this->assertTrue($config->requireSemicolons());
        $this->assertFalse($config->useUnicode());

        $config = new Configuration(['configFile' => __DIR__.'/fixtures/config.php']);

        // Defining a configuration file skips loading local project config
        $this->assertFalse($config->requireSemicolons());
        $this->assertTrue($config->useUnicode());

        \chdir($oldPwd);
    }

    public function testUnknownConfigFileThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration file specified');

        $config = new Configuration(['configFile' => __DIR__.'/not/a/real/config.php']);

        $this->fail();
    }

    public function testBaseDirConfigIsDeprecated()
    {
        $this->expectException(\Psy\Exception\DeprecatedException::class);
        $config = new Configuration(['baseDir' => 'fake']);

        $this->fail();
    }

    private function joinPath(...$parts)
    {
        return \implode(\DIRECTORY_SEPARATOR, $parts);
    }

    public function testConfigIncludes()
    {
        $config = new Configuration([
            'defaultIncludes' => ['/file.php'],
            'configFile'      => __DIR__.'/fixtures/empty.php',
        ]);

        $includes = $config->getDefaultIncludes();
        $this->assertCount(1, $includes);
        $this->assertSame('/file.php', $includes[0]);
    }

    public function testGetOutput()
    {
        $config = $this->getConfig();
        $output = $config->getOutput();

        $this->assertInstanceOf(ShellOutput::class, $output);
    }

    public function getOutputDecoratedProvider()
    {
        return [
            'auto' => [
                null,
                Configuration::COLOR_MODE_AUTO,
            ],
            'forced' => [
                true,
                Configuration::COLOR_MODE_FORCED,
            ],
            'disabled' => [
                false,
                Configuration::COLOR_MODE_DISABLED,
            ],
        ];
    }

    /** @dataProvider getOutputDecoratedProvider */
    public function testGetOutputDecorated($expectation, $colorMode)
    {
        if ($colorMode === Configuration::COLOR_MODE_AUTO) {
            $this->markTestSkipped('This test won\'t work on CI without overriding pipe detection');
        }

        $config = $this->getConfig();
        $config->setColorMode($colorMode);

        $this->assertSame($expectation, $config->getOutputDecorated());
    }

    public function setColorModeValidProvider()
    {
        return [
            'auto'     => [Configuration::COLOR_MODE_AUTO],
            'forced'   => [Configuration::COLOR_MODE_FORCED],
            'disabled' => [Configuration::COLOR_MODE_DISABLED],
        ];
    }

    /** @dataProvider setColorModeValidProvider */
    public function testSetColorModeValid($colorMode)
    {
        $config = $this->getConfig();
        $config->setColorMode($colorMode);

        $this->assertSame($colorMode, $config->colorMode());
    }

    public function testSetColorModeInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid color mode: some invalid mode');

        $config = $this->getConfig();
        $config->setColorMode('some invalid mode');

        $this->fail();
    }

    public function getOutputVerbosityProvider()
    {
        return [
            'quiet'        => [OutputInterface::VERBOSITY_QUIET, Configuration::VERBOSITY_QUIET],
            'normal'       => [OutputInterface::VERBOSITY_NORMAL, Configuration::VERBOSITY_NORMAL],
            'verbose'      => [OutputInterface::VERBOSITY_VERBOSE, Configuration::VERBOSITY_VERBOSE],
            'very_verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, Configuration::VERBOSITY_VERY_VERBOSE],
            'debug'        => [OutputInterface::VERBOSITY_DEBUG, Configuration::VERBOSITY_DEBUG],
        ];
    }

    /**
     * @dataProvider getOutputVerbosityProvider
     *
     * @group isolation-fail
     */
    public function testGetOutputVerbosity($expectation, $verbosity)
    {
        $config = $this->getConfig();
        $config->setVerbosity($verbosity);

        $this->assertSame($expectation, $config->getOutputVerbosity());
    }

    public function setVerbosityValidProvider()
    {
        return [
            'quiet'        => [Configuration::VERBOSITY_QUIET],
            'normal'       => [Configuration::VERBOSITY_NORMAL],
            'verbose'      => [Configuration::VERBOSITY_VERBOSE],
            'very_verbose' => [Configuration::VERBOSITY_VERY_VERBOSE],
            'debug'        => [Configuration::VERBOSITY_DEBUG],
        ];
    }

    /** @dataProvider setVerbosityValidProvider */
    public function testSetVerbosityValid($verbosity)
    {
        $config = $this->getConfig();
        $config->setVerbosity($verbosity);

        $this->assertSame($verbosity, $config->verbosity());
    }

    public function testSetVerbosityInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid verbosity level: some invalid verbosity');

        $config = $this->getConfig();
        $config->setVerbosity('some invalid verbosity');

        $this->fail();
    }

    public function getInputInteractiveProvider()
    {
        return [
            'auto' => [
                null,
                Configuration::INTERACTIVE_MODE_AUTO,
            ],
            'forced' => [
                true,
                Configuration::INTERACTIVE_MODE_FORCED,
            ],
            'disabled' => [
                false,
                Configuration::INTERACTIVE_MODE_DISABLED,
            ],
        ];
    }

    /** @dataProvider getInputInteractiveProvider */
    public function testGetInputInteractive($expectation, $interactive)
    {
        if ($interactive === Configuration::INTERACTIVE_MODE_AUTO) {
            $this->markTestSkipped('This test won\'t work on CI without overriding pipe detection');
        }

        $config = $this->getConfig();
        $config->setInteractiveMode($interactive);

        $this->assertSame($expectation, $config->getInputInteractive());
    }

    public function setInteractiveModeValidProvider()
    {
        return [
            'auto'     => [Configuration::INTERACTIVE_MODE_AUTO],
            'forced'   => [Configuration::INTERACTIVE_MODE_FORCED],
            'disabled' => [Configuration::INTERACTIVE_MODE_DISABLED],
        ];
    }

    /** @dataProvider setInteractiveModeValidProvider */
    public function testsetInteractiveModeValid($interactive)
    {
        $config = $this->getConfig();
        $config->setInteractiveMode($interactive);

        $this->assertSame($interactive, $config->interactiveMode());
    }

    public function testsetInteractiveModeInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid interactive mode: nope');

        $config = $this->getConfig();
        $config->setInteractiveMode('nope');

        $this->fail();
    }

    public function testSetCheckerValid()
    {
        $config = $this->getConfig();
        $checker = new GitHubChecker();

        $config->setChecker($checker);

        $this->assertSame($checker, $config->getChecker());
    }

    public function testSetFormatterStyles()
    {
        $config = $this->getConfig();
        $config->setFormatterStyles([
            'mario' => ['white', 'red'],
            'luigi' => ['white', 'green'],
        ]);

        $formatter = $config->getOutput()->getFormatter();

        $this->assertTrue($formatter->hasStyle('mario'));
        $this->assertTrue($formatter->hasStyle('luigi'));

        $mario = $formatter->getStyle('mario');
        $this->assertSame("\e[37;41mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertSame("\e[37;42mwheee\e[39;49m", $luigi->apply('wheee'));
    }

    public function testSetFormatterStylesRuntimeUpdates()
    {
        $config = $this->getConfig();
        $formatter = $config->getOutput()->getFormatter();

        $this->assertFalse($formatter->hasStyle('mario'));
        $this->assertFalse($formatter->hasStyle('luigi'));

        $config->setFormatterStyles([
            'mario' => ['white', 'red'],
            'luigi' => ['white', 'green'],
        ]);

        $this->assertTrue($formatter->hasStyle('mario'));
        $this->assertTrue($formatter->hasStyle('luigi'));

        $mario = $formatter->getStyle('mario');
        $this->assertSame("\e[37;41mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertSame("\e[37;42mwheee\e[39;49m", $luigi->apply('wheee'));

        $config->setFormatterStyles([
            'mario' => ['red', 'white'],
            'luigi' => ['green', 'white'],
        ]);

        $mario = $formatter->getStyle('mario');
        $this->assertSame("\e[31;47mwheee\e[39;49m", $mario->apply('wheee'));

        $luigi = $formatter->getStyle('luigi');
        $this->assertSame("\e[32;47mwheee\e[39;49m", $luigi->apply('wheee'));
    }

    /**
     * @dataProvider invalidStyles
     */
    public function testSetFormatterStylesInvalid($styles, $option)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid');
        $this->expectExceptionMessage($option);

        $config = $this->getConfig();
        $config->setFormatterStyles($styles);

        $this->fail();
    }

    public function invalidStyles()
    {
        return [
            [
                ['error' => ['burgundy', null, ['bold']]],
                '"burgundy"',
            ],
            [
                ['error' => ['red', 'ink', ['bold']]],
                '"ink"',
            ],
            [
                ['error' => ['black', 'red', ['marquee']]],
                '"marquee"',
            ],
        ];
    }

    /**
     * @dataProvider inputStrings
     *
     * @group isolation-fail
     */
    public function testConfigurationFromInput($inputString, $verbosity, $colorMode, $interactiveMode, $rawOutput, $yolo)
    {
        $input = $this->getBoundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());
        $this->assertSame($colorMode, $config->colorMode());
        $this->assertSame($interactiveMode, $config->interactiveMode());
        $this->assertSame($rawOutput, $config->rawOutput());
        $this->assertSame($yolo, $config->yolo());

        $input = $this->getUnboundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());
        $this->assertSame($colorMode, $config->colorMode());
        $this->assertSame($interactiveMode, $config->interactiveMode());
        $this->assertSame($rawOutput, $config->rawOutput());
        $this->assertSame($yolo, $config->yolo());
    }

    public function inputStrings()
    {
        return [
            ['', Configuration::VERBOSITY_NORMAL, Configuration::COLOR_MODE_AUTO, Configuration::INTERACTIVE_MODE_AUTO, false, false],
            ['--raw-output --color --interactive --verbose', Configuration::VERBOSITY_VERBOSE, Configuration::COLOR_MODE_FORCED, Configuration::INTERACTIVE_MODE_FORCED, false, false],
            ['--raw-output --no-color --no-interactive --quiet', Configuration::VERBOSITY_QUIET, Configuration::COLOR_MODE_DISABLED, Configuration::INTERACTIVE_MODE_DISABLED, true, false],
            ['--quiet --color --interactive', Configuration::VERBOSITY_QUIET, Configuration::COLOR_MODE_FORCED, Configuration::INTERACTIVE_MODE_FORCED, false, false],
            ['--quiet --yolo', Configuration::VERBOSITY_QUIET, Configuration::COLOR_MODE_AUTO, Configuration::INTERACTIVE_MODE_AUTO, false, true],
        ];
    }

    /**
     * @group isolation-fail
     */
    public function testConfigurationFromInputSpecificity()
    {
        $input = $this->getBoundStringInput('--raw-output --color --interactive --verbose');
        $config = Configuration::fromInput($input);
        $this->assertSame(Configuration::VERBOSITY_VERBOSE, $config->verbosity());
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode());
        $this->assertSame(Configuration::INTERACTIVE_MODE_FORCED, $config->interactiveMode());
        $this->assertFalse($config->rawOutput(), '--raw-output is ignored with interactive input');

        $input = $this->getBoundStringInput('--verbose --quiet --color --no-color --interactive --no-interactive');
        $config = Configuration::fromInput($input);
        $this->assertSame(Configuration::VERBOSITY_QUIET, $config->verbosity(), '--quiet trumps --verbose');
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode(), '--color trumps --no-color');
        $this->assertSame(Configuration::INTERACTIVE_MODE_FORCED, $config->interactiveMode(), '--interactive trumps --no-interactive');
    }

    /**
     * @dataProvider verbosityInputStrings
     *
     * @group isolation-fail
     */
    public function testConfigurationFromInputVerbosityLevels($inputString, $verbosity)
    {
        $input = $this->getBoundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());

        $input = $this->getUnboundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());
    }

    public function verbosityInputStrings()
    {
        return [
            ['--verbose 0',  Configuration::VERBOSITY_NORMAL],
            ['--verbose=0',  Configuration::VERBOSITY_NORMAL],
            ['--verbose 1',  Configuration::VERBOSITY_VERBOSE],
            ['--verbose=1',  Configuration::VERBOSITY_VERBOSE],
            ['-v',           Configuration::VERBOSITY_VERBOSE],
            ['--verbose 2',  Configuration::VERBOSITY_VERY_VERBOSE],
            ['--verbose=2',  Configuration::VERBOSITY_VERY_VERBOSE],
            ['-vv',          Configuration::VERBOSITY_VERY_VERBOSE],
            ['--verbose 3',  Configuration::VERBOSITY_DEBUG],
            ['--verbose=3',  Configuration::VERBOSITY_DEBUG],
            ['-vvv',         Configuration::VERBOSITY_DEBUG],
            // no `--verbose -1` because that's not a valid option value :P
            ['--verbose=-1', Configuration::VERBOSITY_QUIET],
            ['--quiet', Configuration::VERBOSITY_QUIET],
        ];
    }

    /**
     * @dataProvider shortInputStrings
     *
     * @group isolation-fail
     */
    public function testConfigurationFromInputShortOptions($inputString, $verbosity, $interactiveMode, $rawOutput, $skipUnbound = false)
    {
        $input = $this->getBoundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());
        $this->assertSame($interactiveMode, $config->interactiveMode());
        $this->assertSame($rawOutput, $config->rawOutput());

        if ($skipUnbound) {
            $this->markTestSkipped($inputString.' fails with unbound input');
        }

        $input = $this->getUnboundStringInput($inputString);
        $config = Configuration::fromInput($input);
        $this->assertSame($verbosity, $config->verbosity());
        $this->assertSame($interactiveMode, $config->interactiveMode());
        $this->assertSame($rawOutput, $config->rawOutput());
    }

    public function shortInputStrings()
    {
        return [
            // Can't do `-nrq`-style compact short options with unbound input.
            ['-nrq',     Configuration::VERBOSITY_QUIET,        Configuration::INTERACTIVE_MODE_DISABLED, true, true],
            ['-n -r -q', Configuration::VERBOSITY_QUIET,        Configuration::INTERACTIVE_MODE_DISABLED, true],
            ['-v',       Configuration::VERBOSITY_VERBOSE,      Configuration::INTERACTIVE_MODE_AUTO,     false],
            ['-vv',      Configuration::VERBOSITY_VERY_VERBOSE, Configuration::INTERACTIVE_MODE_AUTO,     false],
            ['-vvv',     Configuration::VERBOSITY_DEBUG,        Configuration::INTERACTIVE_MODE_AUTO,     false],
        ];
    }

    /**
     * @group isolation-fail
     */
    public function testConfigurationFromInputAliases()
    {
        $input = $this->getBoundStringInput('--ansi --interaction');
        $config = Configuration::fromInput($input);
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode());
        $this->assertSame(Configuration::INTERACTIVE_MODE_FORCED, $config->interactiveMode());

        $input = $this->getBoundStringInput('--no-ansi --no-interaction');
        $config = Configuration::fromInput($input);
        $this->assertSame(Configuration::COLOR_MODE_DISABLED, $config->colorMode());
        $this->assertSame(Configuration::INTERACTIVE_MODE_DISABLED, $config->interactiveMode());
    }

    private function getBoundStringInput($string, $configFile = null)
    {
        $input = $this->getUnboundStringInput($string, $configFile);
        $input->bind(new InputDefinition(Configuration::getInputOptions()));

        return $input;
    }

    private function getUnboundStringInput($string, $configFile = null)
    {
        if ($configFile === null) {
            $configFile = __DIR__.'/fixtures/empty.php';
        }

        return new StringInput($string.' --config '.\escapeshellarg($configFile));
    }

    public function testYoloMode()
    {
        $config = $this->getConfig();
        $this->assertFalse($config->yolo());

        $config->setYolo(true);
        $this->assertTrue($config->yolo());

        // The CodeCleaner will not be updated after the first time we access it:
        $this->assertTrue($config->getCodeCleaner()->yolo());
    }
}
