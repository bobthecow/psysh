<?php

/*
 * This file is part of PsySH
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Application;
use Psy\CodeCleaner;
use Psy\Loop\Loop;
use Psy\Loop\ForkingLoop;
use Psy\Output\ShellOutput;
use Psy\Output\OutputPager;
use Psy\Output\ProcOutputPager;
use Psy\Output\PassthruPager;
use Symfony\Component\Console\Application as BaseApplication;

class Configuration
{
    private $baseDir;
    private $tempDir;
    private $configFile;
    private $historyFile;
    private $hasReadline;
    private $useReadline;
    private $hasPcntl;
    private $usePcntl;
    private $forkEveryN = 5;
    private $newCommands = array();

    // services
    private $output;
    private $application;
    private $cleaner;
    private $pager;
    private $loop;

    public function __construct(array $config = array())
    {
        // base configuration
        $this->baseDir     = isset($config['baseDir'])    ? $config['baseDir']    : getenv('HOME').'/.psysh';
        $this->configFile  = isset($config['configFile']) ? $config['configFile'] : $this->baseDir . '/rc.php';

        // make sure there's a baseDir
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        unset($config['baseDir'], $config['configFile']);


        // go go gadget, config!
        $this->loadConfig($config);
        $this->init();
    }

    public function init()
    {
        // feature detection
        $this->hasReadline = function_exists('readline');
        $this->hasPcntl    = function_exists('pcntl_signal');

        if (file_exists($this->configFile)) {
            $this->loadConfigFile($this->configFile);
        }
    }

    public function loadConfig(array $options)
    {
        foreach (array('useReadline', 'usePcntl', 'forkEveryN', 'application', 'cleaner', 'pager', 'loop', 'tmpDir') as $option) {
            if (isset($options[$option])) {
                $method = 'set'.ucfirst($option);
                $this->$method($options[$option]);
            }
        }

        foreach (array('commands') as $option) {
            if (isset($options[$option])) {
                $method = 'add'.ucfirst($option);
                $this->$method($options[$option]);
            }
        }
    }

    public function loadConfigFile($file)
    {
        $__psysh_config_file__ = $file;
        $load = function($config) use ($__psysh_config_file__) {
            return require $__psysh_config_file__;
        };
        $result = $load($this);

        if (!empty($result)) {
            if (is_array($result)) {
                $this->loadConfig($result);
            } else {
                throw new \InvalidArgumentException('PsySH configuration must return an array of options');
            }
        }
    }

    public function setTempDir($dir)
    {
        $this->tempDir = $dir;
    }

    public function getTempDir()
    {
        return $this->tempDir ?: sys_get_temp_dir().'/phpsh/';
    }

    public function setHistoryFile($file)
    {
        $this->historyFile = (string) $historyFile;
    }

    public function getHistoryFile()
    {
        return $this->historyFile ?: $this->baseDir.'/history';
    }

    public function getForkHistoryFile($parentPid)
    {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            mkdir($tempDir);
        }

        return tempnam($tempDir, 'fork_'.$parentPid.'_');
    }

    public function getTempFile($type, $parentPid)
    {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            mkdir($tempDir);
        }

        return tempnam($tempDir, $type.'_'.$parentPid.'_');
    }

    public function getPipe($type, $parentPid)
    {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            mkdir($tempDir);
        }

        return sprintf('%s/%s_%s', $tempDir, $type, $parentPid);
    }

    public function hasReadline()
    {
        return $this->hasReadline;
    }

    public function setUseReadline($useReadline)
    {
        $this->useReadline = (bool) $useReadline;
    }

    public function useReadline()
    {
        return isset($this->useReadline) ? $this->useReadline : $this->hasReadline;
    }

    public function hasPcntl()
    {
        return $this->hasPcntl;
    }

    public function setUsePcntl($usePcntl)
    {
        $this->usePcntl = (bool) $usePcntl;
    }

    public function usePcntl()
    {
        return isset($this->usePcntl) ? $this->usePcntl : $this->hasPcntl;
    }

    public function getForkEveryN()
    {
        return $this->forkEveryN;
    }

    public function setForkEveryN($forkEveryN)
    {
        $this->forkEveryN = (int) $forkEveryN;
    }

    public function setApplication(BaseApplication $application)
    {
        $this->application = $application;
    }

    public function getApplication()
    {
        if (!isset($this->application)) {
            $this->application = new Application;
        }
        $this->doAddCommands();

        return $this->application;
    }

    public function setCodeCleaner(CodeCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    public function getCodeCleaner()
    {
        if (!isset($this->cleaner)) {
            $this->cleaner = new CodeCleaner;
        }

        return $this->cleaner;
    }

    public function setOutput(ShellOutput $output)
    {
        $this->output = $output;
    }

    public function getOutput()
    {
        if (!isset($this->output)) {
            $this->output = new ShellOutput(ShellOutput::VERBOSITY_NORMAL, null, null, $this->getPager());
        }

        return $this->output;
    }

    public function setPager($pager)
    {
        if ($pager && !is_string($pager) && !$pager instanceof OutputPager) {
            throw new \InvalidArgumentException('Unexpected pager instance.');
        }

        $this->pager = $pager;
    }

    public function getPager()
    {
        if (!isset($this->pager) && $this->usePcntl()) {
            // check for the presence of less...
            if ($less = exec('which less')) {
                $this->pager = $less.' -R -S -F -X';
            }
        }

        return $this->pager;
    }

    public function setLoop(Loop $loop)
    {
        $this->loop = $loop;
    }

    public function getLoop()
    {
        if (!isset($this->loop)) {
            if ($this->usePcntl()) {
                $this->loop = new ForkingLoop($this);
            } else {
                $this->loop = new Loop($this);
            }
        }

        return $this->loop;
    }

    public function addCommands(array $commands)
    {
        $this->newCommands = array_merge($this->newCommands, $commands);
        if (isset($this->application)) {
            $this->doAddCommands();
        }
    }

    private function doAddCommands()
    {
        if (!empty($this->newCommands)) {
            $this->application->addCommands($this->newCommands);
            $this->newCommands = array();
        }
    }
}
