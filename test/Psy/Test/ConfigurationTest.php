<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\CodeCleaner;
use Psy\Configuration;
use Psy\ExecutionLoop\Loop;
use Psy\Output\PassthruPager;
use Psy\VersionUpdater\GitHubChecker;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaults()
    {
        $config = new Configuration();

        $this->assertEquals(function_exists('readline'), $config->hasReadline());
        $this->assertEquals(function_exists('readline'), $config->useReadline());
        $this->assertEquals(function_exists('pcntl_signal'), $config->hasPcntl());
        $this->assertEquals(function_exists('pcntl_signal'), $config->usePcntl());
        $this->assertFalse($config->requireSemicolons());
        $this->assertSame(Configuration::COLOR_MODE_AUTO, $config->colorMode());
    }

    public function testGettersAndSetters()
    {
        $config = new Configuration();

        $this->assertNull($config->getDataDir());
        $config->setDataDir('wheee');
        $this->assertEquals('wheee', $config->getDataDir());

        $this->assertNull($config->getConfigDir());
        $config->setConfigDir('wheee');
        $this->assertEquals('wheee', $config->getConfigDir());
    }

    /**
     * @dataProvider directories
     */
    public function testFilesAndDirectories($home, $configFile, $historyFile, $manualDbFile)
    {
        $oldHome = getenv('HOME');
        putenv("HOME=$home");

        $config = new Configuration();
        $this->assertEquals(realpath($configFile),   realpath($config->getConfigFile()));
        $this->assertEquals(realpath($historyFile),  realpath($config->getHistoryFile()));
        $this->assertEquals(realpath($manualDbFile), realpath($config->getManualDbFile()));

        putenv("HOME=$oldHome");
    }

    public function directories()
    {
        $base = realpath(__DIR__ . '/../../fixtures');

        return array(
            array(
                $base . '/default',
                $base . '/default/.config/psysh/config.php',
                $base . '/default/.config/psysh/psysh_history',
                $base . '/default/.local/share/psysh/php_manual.sqlite',
            ),
            array(
                $base . '/legacy',
                $base . '/legacy/.psysh/rc.php',
                $base . '/legacy/.psysh/history',
                $base . '/legacy/.psysh/php_manual.sqlite',
            ),
            array(
                $base . '/mixed',
                $base . '/mixed/.psysh/config.php',
                $base . '/mixed/.psysh/psysh_history',
                null,
            ),
        );
    }

    public function testLoadConfig()
    {
        $config  = new Configuration();
        $cleaner = new CodeCleaner();
        $pager   = new PassthruPager(new ConsoleOutput());
        $loop    = new Loop($config);

        $config->loadConfig(array(
            'useReadline'       => false,
            'usePcntl'          => false,
            'codeCleaner'       => $cleaner,
            'pager'             => $pager,
            'loop'              => $loop,
            'requireSemicolons' => true,
            'errorLoggingLevel' => E_ERROR | E_WARNING,
            'colorMode'         => Configuration::COLOR_MODE_FORCED,
        ));

        $this->assertFalse($config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame($cleaner, $config->getCodeCleaner());
        $this->assertSame($pager, $config->getPager());
        $this->assertSame($loop, $config->getLoop());
        $this->assertTrue($config->requireSemicolons());
        $this->assertEquals(E_ERROR | E_WARNING, $config->errorLoggingLevel());
        $this->assertSame(Configuration::COLOR_MODE_FORCED, $config->colorMode());
    }

    public function testLoadConfigFile()
    {
        $config = new Configuration(array('configFile' => __DIR__ . '/../../fixtures/config.php'));

        $runtimeDir = $this->joinPath(realpath(sys_get_temp_dir()), 'psysh_test', 'withconfig', 'temp');

        $this->assertStringStartsWith($runtimeDir, realpath($config->getTempFile('foo', 123)));
        $this->assertStringStartsWith($runtimeDir, realpath(dirname($config->getPipe('pipe', 123))));
        $this->assertStringStartsWith($runtimeDir, realpath($config->getRuntimeDir()));

        $this->assertEquals(function_exists('readline'), $config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertEquals(E_ALL & ~E_NOTICE, $config->errorLoggingLevel());
    }

    public function testLoadLocalConfigFile()
    {
        $oldPwd = getenv('PWD');
        putenv('PWD=' . realpath(__DIR__ . '/../../fixtures/project/'));

        $config = new Configuration();

        // When no configuration file is specified local project config is merged
        $this->assertFalse($config->useReadline());
        $this->assertTrue($config->usePcntl());

        $config = new Configuration(array('configFile' => __DIR__ . '/../../fixtures/config.php'));

        // Defining a configuration file skips loading local project config
        $this->assertTrue($config->useReadline());
        $this->assertFalse($config->usePcntl());

        putenv("PWD=$oldPwd");
    }

    /**
     * @expectedException Psy\Exception\DeprecatedException
     */
    public function testBaseDirConfigIsDeprecated()
    {
        $config = new Configuration(array('baseDir' => 'fake'));
    }

    private function joinPath()
    {
        return implode(DIRECTORY_SEPARATOR, func_get_args());
    }

    public function testConfigIncludes()
    {
        $config = new Configuration(array(
            'defaultIncludes' => array('/file.php'),
            'configFile'      => __DIR__ . '/../../fixtures/empty.php',
        ));

        $includes = $config->getDefaultIncludes();
        $this->assertCount(1, $includes);
        $this->assertEquals('/file.php', $includes[0]);
    }

    public function testGetOutput()
    {
        $config = new Configuration();
        $output = $config->getOutput();

        $this->assertInstanceOf('\Psy\Output\ShellOutput', $output);
    }

    public function getOutputDecoratedProvider()
    {
        return array(
            'auto' => array(
                null,
                Configuration::COLOR_MODE_AUTO,
            ),
            'forced' => array(
                true,
                Configuration::COLOR_MODE_FORCED,
            ),
            'disabled' => array(
                false,
                Configuration::COLOR_MODE_DISABLED,
            ),
        );
    }

    /** @dataProvider getOutputDecoratedProvider */
    public function testGetOutputDecorated($expectation, $colorMode)
    {
        $config = new Configuration();
        $config->setColorMode($colorMode);

        $this->assertSame($expectation, $config->getOutputDecorated());
    }

    public function setColorModeValidProvider()
    {
        return array(
            'auto'     => array(Configuration::COLOR_MODE_AUTO),
            'forced'   => array(Configuration::COLOR_MODE_FORCED),
            'disabled' => array(Configuration::COLOR_MODE_DISABLED),
        );
    }

    /** @dataProvider setColorModeValidProvider */
    public function testSetColorModeValid($colorMode)
    {
        $config = new Configuration();
        $config->setColorMode($colorMode);

        $this->assertEquals($colorMode, $config->colorMode());
    }

    public function testSetColorModeInvalid()
    {
        $config = new Configuration();
        $colorMode = 'some invalid mode';

        $this->setExpectedException(
            '\InvalidArgumentException',
            'invalid color mode: some invalid mode'
        );
        $config->setColorMode($colorMode);
    }

    public function testSetCheckerValid()
    {
        $config = new Configuration();
        $checker = new GitHubChecker();

        $config->setChecker($checker);

        $this->assertSame($checker, $config->getChecker());
    }
}
