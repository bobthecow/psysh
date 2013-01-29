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

use Psy\Shell;
use Psy\CodeCleaner;
use Psy\ExecutionLoop\Loop;
use Psy\ExecutionLoop\ForkingLoop;
use Psy\Output\ShellOutput;
use Psy\Output\OutputPager;
use Psy\Output\ProcOutputPager;

/**
 * The Psy Shell configuration.
 */
class Configuration
{
    private $defaultIncludes;
    private $baseDir;
    private $tempDir;
    private $configFile;
    private $historyFile;
    private $manualDbFile;
    private $hasReadline;
    private $useReadline;
    private $hasPcntl;
    private $usePcntl;
    private $newCommands = array();

    // services
    private $output;
    private $shell;
    private $cleaner;
    private $pager;
    private $loop;
    private $manualDb;

    /**
     * Construct a Configuration instance.
     *
     * Optionally, supply an array of configuration values to load.
     *
     * @param array $config Optional array of configuration values.
     */
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

    /**
     * Initialize the configuration.
     *
     * This checks for the presence of Readline and Pcntl extensions.
     *
     * If a config file has been specified, it will be loaded and merged with the current config.
     */
    public function init()
    {
        // feature detection
        $this->hasReadline = function_exists('readline');
        $this->hasPcntl    = function_exists('pcntl_signal');

        if (file_exists($this->configFile)) {
            $this->loadConfigFile($this->configFile);
        }
    }

    /**
     * Load configuration values from an array of options.
     *
     * @param array $options
     */
    public function loadConfig(array $options)
    {
        foreach (array('defaultIncludes', 'useReadline', 'usePcntl', 'codeCleaner', 'pager', 'loop', 'tempDir', 'manualDbFile') as $option) {
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

    /**
     * Load a configuration file (default: `$HOME/.psysh/rc.php`).
     *
     * This configuration instance will be available to the config file as $config.
     * The config file may directly manipulate the configuration, or may return
     * an array of options which will be merged with the current configuration.
     *
     * @throws InvalidArgumentException if the config file returns a non-array result.
     *
     * @param string $file
     */
    public function loadConfigFile($file)
    {
        $__psysh_config_file__ = $file;
        $load = function($config) use ($__psysh_config_file__) {
            $result = require $__psysh_config_file__;
            if ($result !== 1) {
                return $result;
            }
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

    /**
     * Set files to be included by default at the start of each shell session.
     *
     * @param array $includes
     */
    public function setDefaultIncludes(array $includes = array())
    {
        $this->defaultIncludes = $includes;
    }

    /**
     * Get files to be included by default at the start of each shell session.
     *
     * @return array
     */
    public function getDefaultIncludes()
    {
        return $this->defaultIncludes ?: array();
    }

    /**
     * Set the shell's temporary directory location.
     *
     * @param string $dir
     */
    public function setTempDir($dir)
    {
        $this->tempDir = $dir;
    }

    /**
     * Get the shell's temporary directory location.
     *
     * Defaults to `/psysh/` inside the system's temp dir unless explicitly
     * overridden.
     *
     * @return string
     */
    public function getTempDir()
    {
        return $this->tempDir ?: sys_get_temp_dir().'/phpsh/';
    }

    /**
     * Set the readline history file path.
     *
     * @param string $file
     */
    public function setHistoryFile($file)
    {
        $this->historyFile = (string) $historyFile;
    }

    /**
     * Get the readline history file path.
     *
     * Defaults to `/history` inside the shell's base config dir unless
     * explicitly overridden.
     *
     * @return string
     */
    public function getHistoryFile()
    {
        return $this->historyFile ?: $this->baseDir.'/history';
    }

    /**
     * Get a temporary file of type $type for process $pid.
     *
     * The file will be created inside the current temporary directory.
     *
     * @see self::getTempDir
     *
     * @param string $type
     * @param int    $pid
     *
     * @return string Temporary file name
     */
    public function getTempFile($type, $pid)
    {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return tempnam($tempDir, $type.'_'.$pid.'_');
    }

    /**
     * Get a filename suitable for a FIFO pipe of $type for process $pid.
     *
     * The pipe will be created inside the current temporary directory.
     *
     * @param string $type
     * @param id     $pid
     *
     * @return string Pipe name
     */
    public function getPipe($type, $pid)
    {
        $tempDir = $this->getTempDir();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        return sprintf('%s/%s_%s', $tempDir, $type, $pid);
    }

    /**
     * Check whether this PHP instance has Readline available.
     *
     * @return bool True if Readline is available.
     */
    public function hasReadline()
    {
        return $this->hasReadline;
    }

    /**
     * Enable or disable Readline usage.
     *
     * @param bool $useReadline
     */
    public function setUseReadline($useReadline)
    {
        $this->useReadline = (bool) $useReadline;
    }

    /**
     * Check whether to use Readline.
     *
     * If `setUseReadline` as been set to true, but Readline is not actually
     * available, this will return false.
     *
     * @return bool True if the current Shell should use Readline.
     */
    public function useReadline()
    {
        return isset($this->useReadline) ? $this->useReadline : $this->hasReadline;
    }

    /**
     * Check whether this PHP instance has Pcntl available.
     *
     * @return bool True if Pcntl is available.
     */
    public function hasPcntl()
    {
        return $this->hasPcntl;
    }

    /**
     * Enable or disable Pcntl usage.
     *
     * @param bool $usePcntl
     */
    public function setUsePcntl($usePcntl)
    {
        $this->usePcntl = (bool) $usePcntl;
    }

    /**
     * Check whether to use Pcntl.
     *
     * If `setUsePcntl` has been set to true, but Pcntl is not actually
     * available, this will return false.
     *
     * @return bool True if the current Shell should use Pcntl.
     */
    public function usePcntl()
    {
        return isset($this->usePcntl) ? $this->usePcntl : $this->hasPcntl;
    }

    /**
     * Set a CodeCleaner service instance.
     *
     * @param CodeCleaner $cleaner
     */
    public function setCodeCleaner(CodeCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    /**
     * Get a CodeCleaner service instance.
     *
     * If none has been explicitly defined, this will create a new instance.
     *
     * @return CodeCleaner
     */
    public function getCodeCleaner()
    {
        if (!isset($this->cleaner)) {
            $this->cleaner = new CodeCleaner;
        }

        return $this->cleaner;
    }

    /**
     * Set the Shell Output service.
     *
     * @param ShellOutput $output
     */
    public function setOutput(ShellOutput $output)
    {
        $this->output = $output;
    }

    /**
     * Get a Shell Output service instance.
     *
     * If none has been explicitly provided, this will create a new instance
     * with VERBOSITY_NORMAL and the output page supplied by self::getPager
     *
     * @see self::getPager
     *
     * @return ShellOutput
     */
    public function getOutput()
    {
        if (!isset($this->output)) {
            $this->output = new ShellOutput(ShellOutput::VERBOSITY_NORMAL, null, null, $this->getPager());
        }

        return $this->output;
    }

    /**
     * Set the OutputPager service.
     *
     * If a string is supplied, a ProcOutputPager will be used which shells out
     * to the specified command.
     *
     * @throws \InvalidArgumentException if $pager is not a string or OutputPager instance.
     *
     * @param string|OutputPager $pager
     */
    public function setPager($pager)
    {
        if ($pager && !is_string($pager) && !$pager instanceof OutputPager) {
            throw new \InvalidArgumentException('Unexpected pager instance.');
        }

        $this->pager = $pager;
    }

    /**
     * Get an OutputPager instance or a command for an external Proc pager.
     *
     * If no Pager has been explicitly provided, and Pcntl is available, this
     * will default to `cli.pager` ini value, falling back to `which less`.
     *
     * @return string|OutputPager
     */
    public function getPager()
    {
        if (!isset($this->pager) && $this->usePcntl()) {
            // use the default pager (5.4+)
            if ($pager = ini_get('cli.pager')) {
                $this->pager = $pager;
            // check for the presence of less...
            } elseif ($less = exec('which less')) {
                $this->pager = $less.' -R -S -F -X';
            }
        }

        return $this->pager;
    }

    /**
     * Set the Shell evaluation Loop service.
     *
     * @param Loop $loop
     */
    public function setLoop(Loop $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Get a Shell evaluation Loop service instance.
     *
     * If none has been explicitly defined, this will create a new instance.
     * If Pcntl is available and enabled, the new instance will be a ForkingLoop.
     *
     * @return Loop
     */
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

    /**
     * Add commands to the Shell.
     *
     * This will buffer new commands in the event that the Shell has not yet
     * been instantiated. This allows the user to specify commands in their
     * config rc file, despite the fact that their file is needed in the Shell
     * constructor.
     *
     * @param array $commands
     */
    public function addCommands(array $commands)
    {
        $this->newCommands = array_merge($this->newCommands, $commands);
        if (isset($this->shell)) {
            $this->doAddCommands();
        }
    }

    /**
     * Internal method for adding commands. This will set any new commands once
     * a Shell is available.
     */
    private function doAddCommands()
    {
        if (!empty($this->newCommands)) {
            $this->shell->addCommands($this->newCommands);
            $this->newCommands = array();
        }
    }

    /**
     * Set the Shell backreference and add any new commands to the Shell.
     *
     * @param Shell $shell
     */
    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
        $this->doAddCommands();
    }

    public function setManualDbFile($filename)
    {
        $this->manualDbFile = (string) $filename;
    }

    public function getManualDb()
    {
        if (!isset($this->manualDb)) {
            $dbFile = $this->getManualDbFile();
            if (is_file($dbFile)) {
                $this->manualDb = new \PDO('sqlite:'.$dbFile);
            }
        }

        return $this->manualDb;
    }

    public function getManualDbFile()
    {
        return $this->manualDbFile ?: $this->baseDir.'/php_manual.sqlite';
    }
}
