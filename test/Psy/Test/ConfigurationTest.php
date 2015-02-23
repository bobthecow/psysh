<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test;

use Psy\Configuration;
use Psy\CodeCleaner;
use Psy\Output\PassthruPager;
use Psy\ExecutionLoop\Loop;
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
        ));

        $this->assertFalse($config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame($cleaner, $config->getCodeCleaner());
        $this->assertSame($pager, $config->getPager());
        $this->assertSame($loop, $config->getLoop());
        $this->assertTrue($config->requireSemicolons());
    }

    public function testLoadConfigFile()
    {
        $config = new Configuration(array('configFile' => __DIR__ . '/../../fixtures/config.php'));

        $runtimeDir = $this->joinPath(realpath(sys_get_temp_dir()), 'psysh_test', 'withconfig', 'temp');

        $this->assertStringStartsWith($runtimeDir, realpath($config->getTempFile('foo', 123)));
        $this->assertStringStartsWith($runtimeDir, realpath(dirname($config->getPipe('pipe', 123))));

        // This will be deprecated, but we want to actually test the value.
        $was = error_reporting(error_reporting() & ~E_USER_DEPRECATED);
        $this->assertStringStartsWith($runtimeDir, realpath($config->getTempDir()));
        error_reporting($was);

        $this->assertStringStartsWith($runtimeDir, realpath($config->getRuntimeDir()));

        $this->assertEquals(function_exists('readline'), $config->useReadline());
        $this->assertFalse($config->usePcntl());
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     */
    public function testSetTempDirIsDeprecated()
    {
        $config = new Configuration();
        $config->setTempDir('fake');
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     */
    public function testGetTempDirIsDeprecated()
    {
        $config = new Configuration();
        $config->getTempDir();
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
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
}
