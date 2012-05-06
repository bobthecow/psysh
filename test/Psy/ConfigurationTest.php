<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
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
        $config = new Configuration;

        $this->assertEquals(function_exists('readline'), $config->hasReadline());
        $this->assertEquals(function_exists('readline'), $config->useReadline());
        $this->assertEquals(function_exists('pcntl_signal'), $config->hasPcntl());
        $this->assertEquals(function_exists('pcntl_signal'), $config->usePcntl());
    }

    /**
     * @dataProvider directories
     */
    public function testFilesAndDirectories($baseDir = null, $tempDir = null)
    {
        $config = new Configuration(array('baseDir' => $baseDir, 'tempDir' => $tempDir));

        $this->assertStringEndsWith('/history', $config->getHistoryFile());

        if ($baseDir !== null) {
            $this->assertEquals($baseDir, realpath(dirname($config->getHistoryFile())));
            $this->assertStringEndsWith('/history', $config->getHistoryFile());
            $this->assertTrue(is_dir($baseDir));
        }

        if ($tempDir === null) {
            $sysTempDir = realpath(sys_get_temp_dir());
            $this->assertStringStartsWith($sysTempDir, realpath($config->getTempFile('foo', 123)));
            $this->assertStringStartsWith($sysTempDir, realpath(dirname($config->getPipe('pipe', 123))));
            $this->assertStringStartsWith($sysTempDir, realpath($config->getTempDir()));
        } else {
            $this->assertStringStartsWith($tempDir, realpath($config->getTempFile('foo', 123)));
            $this->assertStringStartsWith($tempDir, realpath(dirname($config->getPipe('pipe', 123))));
            $this->assertStringStartsWith($tempDir, realpath($config->getTempDir()));
        }
    }

    public function directories()
    {
        $base = realpath(sys_get_temp_dir()).'/phpsh_test';

        return array(
            array(null, null),
            array($base.'/base/1', null),
            array($base.'/base/1', $base.'/temp/1'),
            array(null, $base),
            array($base.'/base/2', $base.'/temp/2'),
        );
    }


    public function testLoadConfig()
    {
        $config  = new Configuration;
        $cleaner = new CodeCleaner;
        $pager   = new PassthruPager(new ConsoleOutput);
        $loop    = new Loop($config);

        $config->loadConfig(array(
            'useReadline' => false,
            'usePcntl'    => false,
            'codeCleaner' => $cleaner,
            'pager'       => $pager,
            'loop'        => $loop,
        ));

        $this->assertFalse($config->useReadline());
        $this->assertFalse($config->usePcntl());
        $this->assertSame($cleaner, $config->getCodeCleaner());
        $this->assertSame($pager, $config->getPager());
        $this->assertSame($loop, $config->getLoop());
    }

    public function testLoadConfigFile()
    {
        $config = new Configuration(array('configFile' => __DIR__.'/../fixtures/rc.php'));

        $tempDir = realpath(sys_get_temp_dir()).'/phpsh_test/withconfig/temp';
        $this->assertStringStartsWith($tempDir, realpath($config->getTempFile('foo', 123)));
        $this->assertStringStartsWith($tempDir, realpath(dirname($config->getPipe('pipe', 123))));
        $this->assertStringStartsWith($tempDir, realpath($config->getTempDir()));

        $this->assertEquals(function_exists('readline'), $config->useReadline());
        $this->assertFalse($config->usePcntl());
    }
}
