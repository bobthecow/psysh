<?php

namespace Psy;

use Psy\Application;
use Psy\CodeCleaner;
use Psy\Output;
use Symfony\Component\Console\Application as BaseApplication;

class Configuration
{
    private $configFile;
    private $historyFile;
    private $useReadline;
    private $usePcntl;
    private $newCommands = array();

    public function __construct(array $config = array())
    {
        // base configuration
        $this->baseDir     = isset($config['baseDir'])    ? $config['baseDir']    : getenv('HOME');
        $this->configFile  = isset($config['configFile']) ? $config['configFile'] : $this->baseDir . '/.psyshrc.php';

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
        foreach (array('useReadline', 'usePcntl', 'application', 'cleaner') as $option) {
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

    public function loadConfigFile($file) {
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

    public function setHistoryFile($file)
    {
        $this->historyFile = (string) $historyFile;
    }

    public function getHistoryFile()
    {
        return $this->historyFile ?: $this->baseDir . '/.psysh_history';
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

    public function setOutput(Output $output)
    {
        $this->output = $output;
    }

    public function getOutput()
    {
        if (!isset($this->output)) {
            $this->output = new Output;
        }

        return $this->output;
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
