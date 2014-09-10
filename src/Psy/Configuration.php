<?php

/*
 * This file is part of Psy Shell
 *
 * (c) 2012-2014 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Exception\RuntimeException;
use Psy\ExecutionLoop\ForkingLoop;
use Psy\ExecutionLoop\Loop;
use Psy\Output\OutputPager;
use Psy\Output\ShellOutput;
use Psy\Presenter\PresenterManager;
use Psy\Readline\GNUReadline;
use Psy\Readline\Libedit;
use Psy\Readline\Readline;
use Psy\Readline\Transient;
use XdgBaseDir\Xdg;

/**
 * The Psy Shell configuration.
 */
class Configuration
{
    private static $AVAILABLE_OPTIONS = array(
        'defaultIncludes', 'useReadline', 'usePcntl', 'codeCleaner', 'pager',
        'loop', 'configDir', 'dataDir', 'runtimeDir', 'manualDbFile',
        'presenters', 'requireSemicolons', 'historySize', 'eraseDuplicates',
    );

    private $defaultIncludes;
    private $configDir;
    private $dataDir;
    private $runtimeDir;
    private $configFile;
    private $historyFile;
    private $historySize;
    private $eraseDuplicates;
    private $manualDbFile;
    private $hasReadline;
    private $useReadline;
    private $hasPcntl;
    private $usePcntl;
    private $newCommands = array();
    private $requireSemicolons = false;

    // services
    private $readline;
    private $output;
    private $shell;
    private $cleaner;
    private $pager;
    private $loop;
    private $manualDb;
    private $presenters;

    /**
     * Construct a Configuration instance.
     *
     * Optionally, supply an array of configuration values to load.
     *
     * @param array $config Optional array of configuration values.
     */
    public function __construct(array $config = array())
    {
        // explicit configFile option
        if (isset($config['configFile'])) {
            $this->configFile = $config['configFile'];
        } elseif ($configFile = getenv('PSYSH_CONFIG')) {
            $this->configFile = $configFile;
        }

        // legacy baseDir option
        if (isset($config['baseDir'])) {
            $this->setConfigDir($config['baseDir']);
            $this->setDataDir($config['baseDir']);
        }

        unset($config['configFile'], $config['baseDir']);

        // go go gadget, config!
        $this->loadConfig($config);
        $this->init();
    }

    /**
     * Initialize the configuration.
     *
     * This checks for the presence of Readline and Pcntl extensions.
     *
     * If a config file is available, it will be loaded and merged with the current config.
     */
    public function init()
    {
        // feature detection
        $this->hasReadline = function_exists('readline');
        $this->hasPcntl    = function_exists('pcntl_signal') && function_exists('posix_getpid');

        if ($configFile = $this->getConfigFile()) {
            $this->loadConfigFile($configFile);
        }
    }

    /**
     * Get the current PsySH config file.
     *
     * If a `configFile` option was passed to the Configuration constructor,
     * this file will be returned. If not, all possible config directories will
     * be searched, and the first `config.php` or `rc.php` file which exists
     * will be returned.
     *
     * If you're trying to decide where to put your config file, pick
     *
     *     ~/.config/psysh/config.php
     *
     * @return string
     */
    public function getConfigFile()
    {
        if (isset($this->configFile)) {
            return $this->configFile;
        }

        foreach ($this->getConfigDirs() as $dir) {
            $file = $dir . '/config.php';
            if (is_file($file)) {
                return $this->configFile = $file;
            }

            $file = $dir . '/rc.php';
            if (is_file($file)) {
                return $this->configFile = $file;
            }
        }
    }

    /**
     * Helper function to get the proper home directory.
     *
     * @return string
     */
    private function getHomeDir()
    {
        return getenv('HOME') ?: (getenv('HOMEDRIVE') . '/' . getenv('HOMEPATH'));
    }

    /**
     * Get potential config directory paths.
     *
     * If a `configDir` option was explicitly set, returns an array containing
     * just that directory.
     *
     * Otherwise, it returns `~/.psysh` and all XDG Base Directory config directories:
     *
     *     http://standards.freedesktop.org/basedir-spec/basedir-spec-latest.html
     *
     * @return string[]
     */
    protected function getConfigDirs()
    {
        if (isset($this->configDir)) {
            return array($this->configDir);
        }

        $xdg = new Xdg();
        $dirs = array_map(function ($dir) {
            return $dir . '/psysh';
        }, $xdg->getConfigDirs());

        array_unshift($dirs, $this->getHomeDir() . '/.psysh');

        return $dirs;
    }

    /**
     * Get potential data directory paths.
     *
     * If a `dataDir` option was explicitly set, returns an array containing
     * just that directory.
     *
     * Otherwise, it returns `~/.psysh` and all XDG Base Directory data directories:
     *
     *     http://standards.freedesktop.org/basedir-spec/basedir-spec-latest.html
     *
     * @return string[]
     */
    protected function getDataDirs()
    {
        if (isset($this->dataDir)) {
            return array($this->dataDir);
        }

        $xdg = new Xdg();
        $dirs = array_map(function ($dir) {
            return $dir . '/psysh';
        }, $xdg->getDataDirs());

        array_unshift($dirs, $this->getHomeDir() . '/.psysh');

        return $dirs;
    }

    /**
     * Load configuration values from an array of options.
     *
     * @param array $options
     */
    public function loadConfig(array $options)
    {
        foreach (self::$AVAILABLE_OPTIONS as $option) {
            if (isset($options[$option])) {
                $method = 'set' . ucfirst($option);
                $this->$method($options[$option]);
            }
        }

        foreach (array('commands') as $option) {
            if (isset($options[$option])) {
                $method = 'add' . ucfirst($option);
                $this->$method($options[$option]);
            }
        }
    }

    /**
     * Load a configuration file (default: `$HOME/.config/psysh/config.php`).
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
        $load = function ($config) use ($__psysh_config_file__) {
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
                throw new \InvalidArgumentException('Psy Shell configuration must return an array of options');
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
     * Set the shell's config directory location.
     *
     * @param string $dir
     */
    public function setConfigDir($dir)
    {
        $this->configDir = (string) $dir;
    }

    /**
     * Get the current configuration directory, if any is explicitly set.
     *
     * @return string
     */
    public function getConfigDir()
    {
        return $this->configDir;
    }

    /**
     * Set the shell's data directory location.
     *
     * @param string $dir
     */
    public function setDataDir($dir)
    {
        $this->dataDir = (string) $dir;
    }

    /**
     * Get the current data directory, if any is explicitly set.
     *
     * @return string
     */
    public function getDataDir()
    {
        return $this->dataDir;
    }

    /**
     * Set the shell's temporary directory location.
     *
     * @param string $dir
     */
    public function setRuntimeDir($dir)
    {
        $this->runtimeDir = (string) $dir;
    }

    /**
     * Get the shell's temporary directory location.
     *
     * Defaults to  `/psysh` inside the system's temp dir unless explicitly
     * overridden.
     *
     * @return string
     */
    public function getRuntimeDir()
    {
        if (!isset($this->runtimeDir)) {
            $xdg = new Xdg();
            $this->runtimeDir = $xdg->getRuntimeDir() . '/psysh';
        }

        if (!is_dir($this->runtimeDir)) {
            mkdir($this->runtimeDir, 0700, true);
        }

        return $this->runtimeDir;
    }

    /**
     * @deprecated Use setRuntimeDir() instead.
     *
     * @param string $dir
     */
    public function setTempDir($dir)
    {
        return $this->setRuntimeDir($dir);
    }

    /**
     * @deprecated Use getRuntimeDir() instead.
     *
     * @return string
     */
    public function getTempDir()
    {
        return $this->getRuntimeDir();
    }

    /**
     * Set the readline history file path.
     *
     * @param string $file
     */
    public function setHistoryFile($file)
    {
        $this->historyFile = (string) $file;
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
        if (isset($this->historyFile)) {
            return $this->historyFile;
        }

        foreach ($this->getConfigDirs() as $dir) {
            $file = $dir . '/psysh_history';
            if (is_file($file)) {
                return $this->historyFile = $file;
            }

            $file = $dir . '/history';
            if (is_file($file)) {
                return $this->historyFile = $file;
            }
        }

        // fallback: create our own
        if (isset($this->configDir)) {
            $dir = $this->configDir;
        } else {
            $xdg = new Xdg();
            $dir = $xdg->getHomeConfigDir();
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $file = $dir . '/psysh_history';

        return $this->historyFile = $file;
    }

    /**
     * Set the readline max history size
     *
     * @param int $value
     */
    public function setHistorySize($value)
    {
        $this->historySize = (int) $value;
    }

    /**
     * Get the readline max history size
     *
     * @return int
     */
    public function getHistorySize()
    {
        return $this->historySize;
    }

    /**
     * Sets whether readline erases old duplicate history entries.
     *
     * @param bool $value
     */
    public function setEraseDuplicates($value)
    {
        $this->eraseDuplicates = (bool) $value;
    }

    /**
     * Get whether readline erases old duplicate history entries.
     *
     * @return bool
     */
    public function getEraseDuplicates()
    {
        return $this->eraseDuplicates;
    }

    /**
     * Get a temporary file of type $type for process $pid.
     *
     * The file will be created inside the current temporary directory.
     *
     * @see self::getRuntimeDir
     *
     * @param string $type
     * @param int    $pid
     *
     * @return string Temporary file name
     */
    public function getTempFile($type, $pid)
    {
        return tempnam($this->getRuntimeDir(), $type . '_' . $pid . '_');
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
        return sprintf('%s/%s_%s', $this->getRuntimeDir(), $type, $pid);
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
        return isset($this->useReadline) ? ($this->hasReadline && $this->useReadline) : $this->hasReadline;
    }

    /**
     * Set the Psy Shell readline service.
     *
     * @param Readline $readline
     */
    public function setReadline(Readline $readline)
    {
        $this->readline = $readline;
    }

    /**
     * Get the Psy Shell readline service.
     *
     * By default, this service uses (in order of preference):
     *
     *  * GNU Readline
     *  * Libedit
     *  * A transient array-based readline emulation.
     *
     * @return Readline
     */
    public function getReadline()
    {
        if (!isset($this->readline)) {
            $className = $this->getReadlineClass();
            $this->readline = new $className(
                $this->getHistoryFile(),
                $this->getHistorySize(),
                $this->getEraseDuplicates()
            );
        }

        return $this->readline;
    }

    /**
     * Get the appropriate Readline implementation class name.
     *
     * @see self::getReadline
     *
     * @return string
     */
    private function getReadlineClass()
    {
        if ($this->useReadline()) {
            if (GNUReadline::isSupported()) {
                return 'Psy\Readline\GNUReadline';
            } elseif (Libedit::isSupported()) {
                return 'Psy\Readline\Libedit';
            }
        }

        return 'Psy\Readline\Transient';
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
        return isset($this->usePcntl) ? ($this->hasPcntl && $this->usePcntl) : $this->hasPcntl;
    }

    /**
     * Enable or disable strict requirement of semicolons.
     *
     * @see self::requireSemicolons()
     *
     * @param bool $requireSemicolons
     */
    public function setRequireSemicolons($requireSemicolons)
    {
        $this->requireSemicolons = (bool) $requireSemicolons;
    }

    /**
     * Check whether to require semicolons on all statements.
     *
     * By default, PsySH will automatically insert semicolons at the end of
     * statements if they're missing. To strictly require semicolons, set
     * `requireSemicolons` to true.
     *
     * @return bool
     */
    public function requireSemicolons()
    {
        return $this->requireSemicolons;
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
            $this->cleaner = new CodeCleaner();
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
            if ($pager = ini_get('cli.pager')) {
                // use the default pager (5.4+)
                $this->pager = $pager;
            } elseif ($less = exec('which less 2>/dev/null')) {
                // check for the presence of less...
                $this->pager = $less . ' -R -S -F -X';
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

    /**
     * Set the PHP manual database file.
     *
     * This file should be an SQLite database generated from the phpdoc source
     * with the `bin/build_manual` script.
     *
     * @param string $filename
     */
    public function setManualDbFile($filename)
    {
        $this->manualDbFile = (string) $filename;
    }

    /**
     * Get the current PHP manual database file.
     *
     * @return string Default: '~/.local/share/psysh/php_manual.sqlite'
     */
    public function getManualDbFile()
    {
        if (isset($this->manualDbFile)) {
            return $this->manualDbFile;
        }

        foreach ($this->getDataDirs() as $dir) {
            $file = $dir . '/php_manual.sqlite';
            if (is_file($file)) {
                return $this->manualDbFile = $file;
            }
        }
    }

    /**
     * Get a PHP manual database connection.
     *
     * @return PDO
     */
    public function getManualDb()
    {
        if (!isset($this->manualDb)) {
            $dbFile = $this->getManualDbFile();
            if (is_file($dbFile)) {
                try {
                    $this->manualDb = new \PDO('sqlite:' . $dbFile);
                } catch (\PDOException $e) {
                    if ($e->getMessage() === 'could not find driver') {
                        throw new RuntimeException('SQLite PDO driver not found', 0, $e);
                    } else {
                        throw $e;
                    }
                }
            }
        }

        return $this->manualDb;
    }

    /**
     * Add an array of Presenters.
     *
     * @param array $presenters
     */
    public function addPresenters(array $presenters)
    {
        $this->setPresenters($presenters);
    }

    /**
     * @see self::addPresenters()
     *
     * @param array $presenters (default: array())
     */
    protected function setPresenters(array $presenters = array())
    {
        $manager = $this->getPresenterManager();
        foreach ($presenters as $presenter) {
            $manager->addPresenter($presenter);
        }
    }

    /**
     * Get the PresenterManager service.
     *
     * @return PresenterManager
     */
    public function getPresenterManager()
    {
        if (!isset($this->presenters)) {
            $this->presenters = new PresenterManager();
        }

        return $this->presenters;
    }
}
