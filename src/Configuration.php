<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Exception\DeprecatedException;
use Psy\Exception\InvalidManualException;
use Psy\Exception\RuntimeException;
use Psy\ExecutionLoop\ExecutionLoggingListener;
use Psy\ExecutionLoop\InputLoggingListener;
use Psy\ExecutionLoop\ProcessForker;
use Psy\Formatter\SignatureFormatter;
use Psy\Logger\CallbackLogger;
use Psy\Manual\ManualInterface;
use Psy\Manual\V2Manual;
use Psy\Manual\V3Manual;
use Psy\Output\OutputPager;
use Psy\Output\ShellOutput;
use Psy\Output\Theme;
use Psy\TabCompletion\AutoCompleter;
use Psy\VarDumper\Presenter;
use Psy\VersionUpdater\Checker;
use Psy\VersionUpdater\GitHubChecker;
use Psy\VersionUpdater\IntervalChecker;
use Psy\VersionUpdater\NoopChecker;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Psy Shell configuration.
 */
class Configuration
{
    const COLOR_MODE_AUTO = 'auto';
    const COLOR_MODE_FORCED = 'forced';
    const COLOR_MODE_DISABLED = 'disabled';

    const INTERACTIVE_MODE_AUTO = 'auto';
    const INTERACTIVE_MODE_FORCED = 'forced';
    const INTERACTIVE_MODE_DISABLED = 'disabled';

    const VERBOSITY_QUIET = 'quiet';
    const VERBOSITY_NORMAL = 'normal';
    const VERBOSITY_VERBOSE = 'verbose';
    const VERBOSITY_VERY_VERBOSE = 'very_verbose';
    const VERBOSITY_DEBUG = 'debug';

    private const AVAILABLE_OPTIONS = [
        'codeCleaner',
        'colorMode',
        'configDir',
        'dataDir',
        'defaultIncludes',
        'eraseDuplicates',
        'errorLoggingLevel',
        'forceArrayIndexes',
        'formatterStyles',
        'historyFile',
        'historySize',
        'implicitUse',
        'interactiveMode',
        'logging',
        'manualDbFile',
        'pager',
        'prompt',
        'rawOutput',
        'requireSemicolons',
        'runtimeDir',
        'startupMessage',
        'strictTypes',
        'theme',
        'updateCheck',
        'updateManualCheck',
        'useBracketedPaste',
        'usePcntl',
        'useReadline',
        'useTabCompletion',
        'useUnicode',
        'verbosity',
        'warmAutoload',
        'warnOnMultipleConfigs',
        'yolo',
    ];

    private ?array $defaultIncludes = null;
    private ?string $configDir = null;
    private ?string $dataDir = null;
    private ?string $runtimeDir = null;
    private ?string $configFile = null;
    /** @var string|false|null */
    private $historyFile;
    private int $historySize = 0;
    private ?bool $eraseDuplicates = null;
    private ?string $manualDbFile = null;
    private bool $hasReadline;
    private ?bool $useReadline = null;
    private bool $useBracketedPaste = false;
    private bool $hasPcntl;
    private ?bool $usePcntl = null;
    private array $newCommands = [];
    private ?bool $pipedInput = null;
    private ?bool $pipedOutput = null;
    private bool $rawOutput = false;
    private bool $requireSemicolons = false;
    private bool $strictTypes = false;
    private ?bool $useUnicode = null;
    private ?bool $useTabCompletion = null;
    private array $newMatchers = [];
    private ?array $autoloadWarmers = null;
    private $implicitUse = false;
    private ?ShellLogger $logger = null;
    private int $errorLoggingLevel = \E_ALL;
    private bool $warnOnMultipleConfigs = false;
    private string $colorMode = self::COLOR_MODE_AUTO;
    private string $interactiveMode = self::INTERACTIVE_MODE_AUTO;
    private ?string $updateCheck = null;
    private ?string $updateManualCheck = null;
    private ?string $startupMessage = null;
    private bool $forceArrayIndexes = false;
    /** @deprecated */
    private array $formatterStyles = [];
    private string $verbosity = self::VERBOSITY_NORMAL;
    private bool $yolo = false;
    private ?Theme $theme = null;

    // services
    private ?Readline\Readline $readline = null;
    private ?ShellOutput $output = null;
    private ?Shell $shell = null;
    private ?CodeCleaner $cleaner = null;
    /** @var string|OutputPager|false|null */
    private $pager = null;
    private ?\PDO $manualDb = null;
    private ?ManualInterface $manual = null;
    private ?Presenter $presenter = null;
    private ?AutoCompleter $autoCompleter = null;
    private ?Checker $checker = null;
    /** @deprecated */
    private ?string $prompt = null;
    private ConfigPaths $configPaths;

    /**
     * Construct a Configuration instance.
     *
     * Optionally, supply an array of configuration values to load.
     *
     * @param array $config Optional array of configuration values
     */
    public function __construct(array $config = [])
    {
        $this->configPaths = new ConfigPaths();

        // explicit configFile option
        if (isset($config['configFile'])) {
            $this->configFile = $config['configFile'];
        } elseif (isset($_SERVER['PSYSH_CONFIG']) && $_SERVER['PSYSH_CONFIG']) {
            $this->configFile = $_SERVER['PSYSH_CONFIG'];
        } elseif (\PHP_SAPI === 'cli-server' && ($configFile = \getenv('PSYSH_CONFIG'))) {
            $this->configFile = $configFile;
        }

        // legacy baseDir option
        if (isset($config['baseDir'])) {
            $msg = "The 'baseDir' configuration option is deprecated; ".
                "please specify 'configDir' and 'dataDir' options instead";
            throw new DeprecatedException($msg);
        }

        unset($config['configFile'], $config['baseDir']);

        // go go gadget, config!
        $this->loadConfig($config);
        $this->init();
    }

    /**
     * Construct a Configuration object from Symfony Console input.
     *
     * This is great for adding psysh-compatible command line options to framework- or app-specific
     * wrappers.
     *
     * $input should already be bound to an appropriate InputDefinition (see self::getInputOptions
     * if you want to build your own) before calling this method. It's not required, but things work
     * a lot better if we do.
     *
     * @see self::getInputOptions
     *
     * @throws \InvalidArgumentException
     *
     * @param InputInterface $input
     */
    public static function fromInput(InputInterface $input): self
    {
        $config = new self(['configFile' => self::getConfigFileFromInput($input)]);

        // Handle --color and --no-color (and --ansi and --no-ansi aliases)
        if (self::getOptionFromInput($input, ['color', 'ansi'])) {
            $config->setColorMode(self::COLOR_MODE_FORCED);
        } elseif (self::getOptionFromInput($input, ['no-color', 'no-ansi'])) {
            $config->setColorMode(self::COLOR_MODE_DISABLED);
        }

        // Handle verbosity options
        if ($verbosity = self::getVerbosityFromInput($input)) {
            $config->setVerbosity($verbosity);
        }

        // Handle interactive mode
        if (self::getOptionFromInput($input, ['interactive', 'interaction'], ['-a', '-i'])) {
            $config->setInteractiveMode(self::INTERACTIVE_MODE_FORCED);
        } elseif (self::getOptionFromInput($input, ['no-interactive', 'no-interaction'], ['-n'])) {
            $config->setInteractiveMode(self::INTERACTIVE_MODE_DISABLED);
        }

        // Handle --compact
        if (self::getOptionFromInput($input, ['compact'])) {
            $config->setTheme('compact');
        }

        // Handle --raw-output
        // @todo support raw output with interactive input?
        if (!$config->getInputInteractive()) {
            if (self::getOptionFromInput($input, ['raw-output'], ['-r'])) {
                $config->setRawOutput(true);
            }
        }

        // Handle --warm-autoload
        if (self::getOptionFromInput($input, ['warm-autoload'])) {
            $config->setWarmAutoload(true);
        }

        // Handle --yolo
        if (self::getOptionFromInput($input, ['yolo'])) {
            $config->setYolo(true);
        }

        return $config;
    }

    /**
     * Get the desired config file from the given input.
     *
     * @return string|null config file path, or null if none is specified
     */
    private static function getConfigFileFromInput(InputInterface $input)
    {
        // Best case, input is properly bound and validated.
        if ($input->hasOption('config')) {
            return $input->getOption('config');
        }

        return $input->getParameterOption('--config', null, true) ?: $input->getParameterOption('-c', null, true);
    }

    /**
     * Get a boolean option from the given input.
     *
     * This helper allows fallback for unbound and unvalidated input. It's not perfect--for example,
     * it can't deal with several short options squished together--but it's better than falling over
     * any time someone gives us unbound input.
     *
     * @return bool true if the option (or an alias) is present
     */
    private static function getOptionFromInput(InputInterface $input, array $names, array $otherParams = []): bool
    {
        // Best case, input is properly bound and validated.
        foreach ($names as $name) {
            if ($input->hasOption($name) && $input->getOption($name)) {
                return true;
            }
        }

        foreach ($names as $name) {
            $otherParams[] = '--'.$name;
        }

        foreach ($otherParams as $name) {
            if ($input->hasParameterOption($name, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the desired verbosity from the given input.
     *
     * This is a bit more complext than the other options parsers. It handles `--quiet` and
     * `--verbose`, along with their short aliases, and fancy things like `-vvv`.
     *
     * @return string|null configuration constant, or null if no verbosity option is specified
     */
    private static function getVerbosityFromInput(InputInterface $input)
    {
        // --quiet wins!
        if (self::getOptionFromInput($input, ['quiet'], ['-q'])) {
            return self::VERBOSITY_QUIET;
        }

        // Best case, input is properly bound and validated.
        //
        // Note that if the `--verbose` option is incorrectly defined as `VALUE_NONE` rather than
        // `VALUE_OPTIONAL` (as it is in Symfony Console by default) it doesn't actually work with
        // multiple verbosity levels as it claims.
        //
        // We can detect this by checking whether the the value === true, and fall back to unbound
        // parsing for this option.
        if ($input->hasOption('verbose') && $input->getOption('verbose') !== true) {
            switch ($input->getOption('verbose')) {
                case '-1':
                    return self::VERBOSITY_QUIET;
                case '0': // explicitly normal, overrides config file default
                    return self::VERBOSITY_NORMAL;
                case '1':
                case null: // `--verbose` and `-v`
                    return self::VERBOSITY_VERBOSE;
                case '2':
                case 'v': // `-vv`
                    return self::VERBOSITY_VERY_VERBOSE;
                case '3':
                case 'vv': // `-vvv`
                case 'vvv':
                case 'vvvv':
                case 'vvvvv':
                case 'vvvvvv':
                case 'vvvvvvv':
                    return self::VERBOSITY_DEBUG;
                default: // implicitly normal, config file default wins
                    return null;
            }
        }

        // quiet and normal have to come before verbose, because it eats everything else.
        if ($input->hasParameterOption('--verbose=-1', true) || $input->getParameterOption('--verbose', false, true) === '-1') {
            return self::VERBOSITY_QUIET;
        }

        if ($input->hasParameterOption('--verbose=0', true) || $input->getParameterOption('--verbose', false, true) === '0') {
            return self::VERBOSITY_NORMAL;
        }

        // `-vvv`, `-vv` and `-v` have to come in descending length order, because `hasParameterOption` matches prefixes.
        if ($input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || $input->getParameterOption('--verbose', false, true) === '3') {
            return self::VERBOSITY_DEBUG;
        }

        if ($input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || $input->getParameterOption('--verbose', false, true) === '2') {
            return self::VERBOSITY_VERY_VERBOSE;
        }

        if ($input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true)) {
            return self::VERBOSITY_VERBOSE;
        }

        return null;
    }

    /**
     * Get a list of input options expected when initializing Configuration via input.
     *
     * @see self::fromInput
     *
     * @return InputOption[]
     */
    public static function getInputOptions(): array
    {
        return [
            new InputOption('config', 'c', InputOption::VALUE_REQUIRED, 'Use an alternate PsySH config file location.'),
            new InputOption('cwd', null, InputOption::VALUE_REQUIRED, 'Use an alternate working directory.'),

            new InputOption('color', null, InputOption::VALUE_NONE, 'Force colors in output.'),
            new InputOption('no-color', null, InputOption::VALUE_NONE, 'Disable colors in output.'),
            // --ansi and --no-ansi aliases to match Symfony, Composer, etc.
            new InputOption('ansi', null, InputOption::VALUE_NONE, 'Force colors in output.'),
            new InputOption('no-ansi', null, InputOption::VALUE_NONE, 'Disable colors in output.'),

            new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Shhhhhh.'),
            new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_OPTIONAL, 'Increase the verbosity of messages.', '0'),
            new InputOption('compact', null, InputOption::VALUE_NONE, 'Run PsySH with compact output.'),
            new InputOption('interactive', 'i|a', InputOption::VALUE_NONE, 'Force PsySH to run in interactive mode.'),
            new InputOption('no-interactive', 'n', InputOption::VALUE_NONE, 'Run PsySH without interactive input. Requires input from stdin.'),
            // --interaction and --no-interaction aliases for compatibility with Symfony, Composer, etc
            new InputOption('interaction', null, InputOption::VALUE_NONE, 'Force PsySH to run in interactive mode.'),
            new InputOption('no-interaction', null, InputOption::VALUE_NONE, 'Run PsySH without interactive input. Requires input from stdin.'),
            new InputOption('raw-output', 'r', InputOption::VALUE_NONE, 'Print var_export-style return values (for non-interactive input)'),

            new InputOption('self-update', 'u', InputOption::VALUE_NONE, 'Update to the latest version'),

            new InputOption('yolo', null, InputOption::VALUE_NONE, 'Run PsySH with minimal input validation. You probably don\'t want this.'),
            new InputOption('warm-autoload', null, InputOption::VALUE_NONE, 'Enable autoload warming for better tab completion.'),
            new InputOption('info', null, InputOption::VALUE_NONE, 'Display PsySH environment and configuration info.'),
        ];
    }

    /**
     * Initialize the configuration.
     *
     * This checks for the presence of Readline and Pcntl extensions.
     *
     * If a config file is available, it will be loaded and merged with the current config.
     *
     * If no custom config file was specified and a local project config file
     * is available, it will be loaded and merged with the current config.
     */
    public function init()
    {
        // feature detection
        $this->hasReadline = \function_exists('readline');
        $this->hasPcntl = ProcessForker::isSupported();

        if ($configFile = $this->getConfigFile()) {
            $this->loadConfigFile($configFile);
        }

        if (!$this->configFile && $localConfig = $this->getLocalConfigFile()) {
            $this->loadConfigFile($localConfig);
        }

        $this->configPaths->overrideDirs([
            'configDir'  => $this->configDir,
            'dataDir'    => $this->dataDir,
            'runtimeDir' => $this->runtimeDir,
        ]);
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
     * @return string|null
     */
    public function getConfigFile()
    {
        if (isset($this->configFile)) {
            return $this->configFile;
        }

        $files = $this->configPaths->configFiles(['config.php', 'rc.php']);

        if (!empty($files)) {
            if ($this->warnOnMultipleConfigs && \count($files) > 1) {
                $prettyFiles = \array_map([ConfigPaths::class, 'prettyPath'], $files);
                $msg = \sprintf('Multiple configuration files found: %s. Using %s', \implode(', ', $prettyFiles), $prettyFiles[0]);
                \trigger_error($msg, \E_USER_NOTICE);
            }

            return $files[0];
        }

        return null;
    }

    /**
     * Get the local PsySH config file.
     *
     * Searches for a project specific config file `.psysh.php` in the current
     * working directory.
     *
     * @return string|null
     */
    public function getLocalConfigFile()
    {
        $localConfig = \getcwd().'/.psysh.php';

        if (@\is_file($localConfig)) {
            return $localConfig;
        }

        return null;
    }

    /**
     * Load configuration values from an array of options.
     *
     * @param array $options
     */
    public function loadConfig(array $options)
    {
        foreach (self::AVAILABLE_OPTIONS as $option) {
            if (isset($options[$option])) {
                $method = 'set'.\ucfirst($option);
                $this->$method($options[$option]);
            }
        }

        // legacy `tabCompletion` option
        if (isset($options['tabCompletion'])) {
            $msg = '`tabCompletion` is deprecated; use `useTabCompletion` instead.';
            @\trigger_error($msg, \E_USER_DEPRECATED);

            $this->setUseTabCompletion($options['tabCompletion']);
        }

        foreach (['commands', 'matchers', 'casters'] as $option) {
            if (isset($options[$option])) {
                $method = 'add'.\ucfirst($option);
                $this->$method($options[$option]);
            }
        }

        // legacy `tabCompletionMatchers` option
        if (isset($options['tabCompletionMatchers'])) {
            $msg = '`tabCompletionMatchers` is deprecated; use `matchers` instead.';
            @\trigger_error($msg, \E_USER_DEPRECATED);

            $this->addMatchers($options['tabCompletionMatchers']);
        }
    }

    /**
     * Load a configuration file (default: `$HOME/.config/psysh/config.php`).
     *
     * This configuration instance will be available to the config file as $config.
     * The config file may directly manipulate the configuration, or may return
     * an array of options which will be merged with the current configuration.
     *
     * @throws \InvalidArgumentException if the config file does not exist or returns a non-array result
     *
     * @param string $file
     */
    public function loadConfigFile(string $file)
    {
        if (!\is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('Invalid configuration file specified, %s does not exist', ConfigPaths::prettyPath($file)));
        }

        $__psysh_config_file__ = $file;
        $load = function ($config) use ($__psysh_config_file__) {
            $result = require $__psysh_config_file__;
            if ($result !== 1) {
                return $result;
            }
        };
        $result = $load($this);

        if (!empty($result)) {
            if (\is_array($result)) {
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
    public function setDefaultIncludes(array $includes = [])
    {
        $this->defaultIncludes = $includes;
    }

    /**
     * Get files to be included by default at the start of each shell session.
     *
     * @return string[]
     */
    public function getDefaultIncludes(): array
    {
        return $this->defaultIncludes ?: [];
    }

    /**
     * Set the shell's config directory location.
     *
     * @param string $dir
     */
    public function setConfigDir(string $dir)
    {
        $this->configDir = (string) $dir;

        $this->configPaths->overrideDirs([
            'configDir'  => $this->configDir,
            'dataDir'    => $this->dataDir,
            'runtimeDir' => $this->runtimeDir,
        ]);
    }

    /**
     * Get the current configuration directory, if any is explicitly set.
     *
     * @return string|null
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
    public function setDataDir(string $dir)
    {
        $this->dataDir = (string) $dir;

        $this->configPaths->overrideDirs([
            'configDir'  => $this->configDir,
            'dataDir'    => $this->dataDir,
            'runtimeDir' => $this->runtimeDir,
        ]);
    }

    /**
     * Get the current data directory, if any is explicitly set.
     *
     * @return string|null
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
    public function setRuntimeDir(string $dir)
    {
        $this->runtimeDir = (string) $dir;

        $this->configPaths->overrideDirs([
            'configDir'  => $this->configDir,
            'dataDir'    => $this->dataDir,
            'runtimeDir' => $this->runtimeDir,
        ]);
    }

    /**
     * Get the shell's temporary directory location.
     *
     * Defaults to `/psysh` inside the system's temp dir unless explicitly
     * overridden.
     *
     * @throws RuntimeException if no temporary directory is set and it is not possible to create one
     *
     * @param bool $create False to suppress directory creation if it does not exist
     */
    public function getRuntimeDir($create = true): string
    {
        $runtimeDir = $this->configPaths->runtimeDir();

        if ($create) {
            if (!@ConfigPaths::ensureDir($runtimeDir)) {
                throw new RuntimeException(\sprintf('Unable to create PsySH runtime directory. Make sure PHP is able to write to %s in order to continue.', \dirname($runtimeDir)));
            }
        }

        return $runtimeDir;
    }

    /**
     * Set the readline history file path.
     *
     * @param string $file
     */
    public function setHistoryFile(string $file)
    {
        $this->historyFile = ConfigPaths::touchFileWithMkdir($file);
    }

    /**
     * Get the readline history file path.
     *
     * Defaults to `/history` inside the shell's base config dir unless
     * explicitly overridden.
     */
    public function getHistoryFile(): ?string
    {
        if (isset($this->historyFile)) {
            return $this->historyFile;
        }

        $files = $this->configPaths->configFiles(['psysh_history', 'history']);

        if (!empty($files)) {
            if ($this->warnOnMultipleConfigs && \count($files) > 1) {
                $prettyFiles = \array_map([ConfigPaths::class, 'prettyPath'], $files);
                $msg = \sprintf('Multiple history files found: %s. Using %s', \implode(', ', $prettyFiles), $prettyFiles[0]);
                \trigger_error($msg, \E_USER_NOTICE);
            }

            $this->setHistoryFile($files[0]);
        } else {
            // fallback: create our own history file
            $configDir = $this->configPaths->currentConfigDir();
            if ($configDir === null) {
                return null;
            }

            $this->setHistoryFile($configDir.'/psysh_history');
        }

        return $this->historyFile;
    }

    /**
     * Set the readline max history size.
     *
     * @param int $value
     */
    public function setHistorySize(int $value)
    {
        $this->historySize = (int) $value;
    }

    /**
     * Get the readline max history size.
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
    public function setEraseDuplicates(bool $value)
    {
        $this->eraseDuplicates = $value;
    }

    /**
     * Get whether readline erases old duplicate history entries.
     *
     * @return bool|null
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
    public function getTempFile(string $type, int $pid): string
    {
        return \tempnam($this->getRuntimeDir(), $type.'_'.$pid.'_');
    }

    /**
     * Get a filename suitable for a FIFO pipe of $type for process $pid.
     *
     * The pipe will be created inside the current temporary directory.
     *
     * @param string $type
     * @param int    $pid
     *
     * @return string Pipe name
     */
    public function getPipe(string $type, int $pid): string
    {
        return \sprintf('%s/%s_%s', $this->getRuntimeDir(), $type, $pid);
    }

    /**
     * Check whether this PHP instance has Readline available.
     *
     * @return bool True if Readline is available
     */
    public function hasReadline(): bool
    {
        return $this->hasReadline;
    }

    /**
     * Enable or disable Readline usage.
     *
     * @param bool $useReadline
     */
    public function setUseReadline(bool $useReadline)
    {
        $this->useReadline = (bool) $useReadline;
    }

    /**
     * Check whether to use Readline.
     *
     * If `setUseReadline` as been set to true, but Readline is not actually
     * available, this will return false.
     *
     * @return bool True if the current Shell should use Readline
     */
    public function useReadline(): bool
    {
        return isset($this->useReadline) ? ($this->hasReadline && $this->useReadline) : $this->hasReadline;
    }

    /**
     * Set the Psy Shell readline service.
     *
     * @param Readline\Readline $readline
     */
    public function setReadline(Readline\Readline $readline)
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
     * @return Readline\Readline
     */
    public function getReadline(): Readline\Readline
    {
        if (!isset($this->readline)) {
            $className = $this->getReadlineClass();
            $this->readline = new $className(
                $this->getHistoryFile(),
                $this->getHistorySize(),
                $this->getEraseDuplicates() ?? false
            );
        }

        return $this->readline;
    }

    /**
     * Get the appropriate Readline implementation class name.
     *
     * @see self::getReadline
     */
    private function getReadlineClass(): string
    {
        if ($this->useReadline()) {
            if (Readline\GNUReadline::isSupported()) {
                return Readline\GNUReadline::class;
            } elseif (Readline\Libedit::isSupported()) {
                return Readline\Libedit::class;
            }
        }

        if (Readline\Userland::isSupported()) {
            return Readline\Userland::class;
        }

        return Readline\Transient::class;
    }

    /**
     * Enable or disable bracketed paste.
     *
     * Note that this only works with readline (not libedit) integration for now.
     *
     * @param bool $useBracketedPaste
     */
    public function setUseBracketedPaste(bool $useBracketedPaste)
    {
        $this->useBracketedPaste = (bool) $useBracketedPaste;
    }

    /**
     * Check whether to use bracketed paste with readline.
     *
     * When this works, it's magical. Tabs in pastes don't try to autcomplete.
     * Newlines in paste don't execute code until you get to the end. It makes
     * readline act like you'd expect when pasting.
     *
     * But it often (usually?) does not work. And when it doesn't, it just spews
     * escape codes all over the place and generally makes things ugly :(
     *
     * If `useBracketedPaste` has been set to true, but the current readline
     * implementation is anything besides GNU readline, this will return false.
     *
     * @return bool True if the shell should use bracketed paste
     */
    public function useBracketedPaste(): bool
    {
        $readlineClass = $this->getReadlineClass();

        return $this->useBracketedPaste && $readlineClass::supportsBracketedPaste();

        // @todo mebbe turn this on by default some day?
        // return $readlineClass::supportsBracketedPaste() && $this->useBracketedPaste !== false;
    }

    /**
     * Check whether this PHP instance has Pcntl available.
     *
     * @return bool True if Pcntl is available
     */
    public function hasPcntl(): bool
    {
        return $this->hasPcntl;
    }

    /**
     * Enable or disable Pcntl usage.
     *
     * @param bool $usePcntl
     */
    public function setUsePcntl(bool $usePcntl)
    {
        $this->usePcntl = (bool) $usePcntl;
    }

    /**
     * Check whether to use Pcntl.
     *
     * If `setUsePcntl` has been set to true, but Pcntl is not actually
     * available, this will return false.
     *
     * @return bool True if the current Shell should use Pcntl
     */
    public function usePcntl(): bool
    {
        if (!isset($this->usePcntl)) {
            // Unless pcntl is explicitly *enabled*, don't use it while XDebug is debugging.
            // See https://github.com/bobthecow/psysh/issues/742
            if (\function_exists('xdebug_is_debugger_active') && \xdebug_is_debugger_active()) {
                return false;
            }

            return $this->hasPcntl;
        }

        return $this->hasPcntl && $this->usePcntl;
    }

    /**
     * Check whether to use raw output.
     *
     * This is set by the --raw-output (-r) flag, and really only makes sense
     * when non-interactive, e.g. executing stdin.
     *
     * @return bool true if raw output is enabled
     */
    public function rawOutput(): bool
    {
        return $this->rawOutput;
    }

    /**
     * Enable or disable raw output.
     *
     * @param bool $rawOutput
     */
    public function setRawOutput(bool $rawOutput)
    {
        $this->rawOutput = (bool) $rawOutput;
    }

    /**
     * Enable or disable strict requirement of semicolons.
     *
     * @see self::requireSemicolons()
     *
     * @param bool $requireSemicolons
     */
    public function setRequireSemicolons(bool $requireSemicolons)
    {
        $this->requireSemicolons = (bool) $requireSemicolons;
    }

    /**
     * Check whether to require semicolons on all statements.
     *
     * By default, PsySH will automatically insert semicolons at the end of
     * statements if they're missing. To strictly require semicolons, set
     * `requireSemicolons` to true.
     */
    public function requireSemicolons(): bool
    {
        return $this->requireSemicolons;
    }

    /**
     * Enable or disable strict types enforcement.
     */
    public function setStrictTypes($strictTypes)
    {
        $this->strictTypes = (bool) $strictTypes;
    }

    /**
     * Check whether to enforce strict types.
     */
    public function strictTypes(): bool
    {
        return $this->strictTypes;
    }

    /**
     * Enable or disable Unicode in PsySH specific output.
     *
     * Note that this does not disable Unicode output in general, it just makes
     * it so PsySH won't output any itself.
     *
     * @param bool $useUnicode
     */
    public function setUseUnicode(bool $useUnicode)
    {
        $this->useUnicode = (bool) $useUnicode;
    }

    /**
     * Check whether to use Unicode in PsySH specific output.
     *
     * Note that this does not disable Unicode output in general, it just makes
     * it so PsySH won't output any itself.
     */
    public function useUnicode(): bool
    {
        if (isset($this->useUnicode)) {
            return $this->useUnicode;
        }

        // @todo detect `chsh` != 65001 on Windows and return false
        return true;
    }

    /**
     * Set the error logging level.
     *
     * @see self::errorLoggingLevel
     *
     * @param int $errorLoggingLevel
     */
    public function setErrorLoggingLevel($errorLoggingLevel)
    {
        if (\PHP_VERSION_ID < 80400) {
            $this->errorLoggingLevel = (\E_ALL | \E_STRICT) & $errorLoggingLevel;
        } else {
            $this->errorLoggingLevel = \E_ALL & $errorLoggingLevel;
        }
    }

    /**
     * Get the current error logging level.
     *
     * By default, PsySH will automatically log all errors, regardless of the
     * current `error_reporting` level.
     *
     * Set `errorLoggingLevel` to 0 to prevent logging non-thrown errors. Set it
     * to any valid error_reporting value to log only errors which match that
     * level.
     *
     *     http://php.net/manual/en/function.error-reporting.php
     */
    public function errorLoggingLevel(): int
    {
        return $this->errorLoggingLevel;
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
     */
    public function getCodeCleaner(): CodeCleaner
    {
        if (!isset($this->cleaner)) {
            $this->cleaner = new CodeCleaner(null, null, null, $this->yolo(), $this->strictTypes(), $this->implicitUse);
        }

        return $this->cleaner;
    }

    /**
     * Enable or disable running PsySH without input validation.
     *
     * You don't want this.
     */
    public function setYolo($yolo)
    {
        $this->yolo = (bool) $yolo;
    }

    /**
     * Check whether to disable input validation.
     */
    public function yolo(): bool
    {
        return $this->yolo;
    }

    /**
     * Enable or disable tab completion.
     *
     * @param bool $useTabCompletion
     */
    public function setUseTabCompletion(bool $useTabCompletion)
    {
        $this->useTabCompletion = (bool) $useTabCompletion;
    }

    /**
     * @deprecated Call `setUseTabCompletion` instead
     *
     * @param bool $useTabCompletion
     */
    public function setTabCompletion(bool $useTabCompletion)
    {
        @\trigger_error('`setTabCompletion` is deprecated; call `setUseTabCompletion` instead.', \E_USER_DEPRECATED);

        $this->setUseTabCompletion($useTabCompletion);
    }

    /**
     * Check whether to use tab completion.
     *
     * If `setUseTabCompletion` has been set to true, but readline is not
     * actually available, this will return false.
     *
     * @return bool True if the current Shell should use tab completion
     */
    public function useTabCompletion(): bool
    {
        return isset($this->useTabCompletion) ? ($this->hasReadline && $this->useTabCompletion) : $this->hasReadline;
    }

    /**
     * @deprecated Call `useTabCompletion` instead
     */
    public function getTabCompletion(): bool
    {
        @\trigger_error('`getTabCompletion` is deprecated; call `useTabCompletion` instead.', \E_USER_DEPRECATED);

        return $this->useTabCompletion();
    }

    /**
     * Set the Shell Output service.
     *
     * @param ShellOutput $output
     */
    public function setOutput(ShellOutput $output)
    {
        $this->output = $output;
        $this->pipedOutput = null; // Reset cached pipe info

        if (isset($this->theme)) {
            $output->setTheme($this->theme);
        }

        $this->applyFormatterStyles();
    }

    /**
     * Get a Shell Output service instance.
     *
     * If none has been explicitly provided, this will create a new instance
     * with the configured verbosity and output pager supplied by self::getPager
     *
     * @see self::verbosity
     * @see self::getPager
     */
    public function getOutput(): ShellOutput
    {
        if (!isset($this->output)) {
            $this->setOutput(new ShellOutput(
                $this->getOutputVerbosity(),
                null,
                null,
                $this->getPager() ?: null,
                $this->theme()
            ));

            // This is racy because `getOutputDecorated` needs access to the
            // output stream to figure out if it's piped or not, so create it
            // first, then update after we have a stream.
            $decorated = $this->getOutputDecorated();
            if ($decorated !== null && $this->output !== null) {
                $this->output->setDecorated($decorated);
            }
        }

        return $this->output;
    }

    /**
     * Get the decoration (i.e. color) setting for the Shell Output service.
     *
     * @return bool|null 3-state boolean corresponding to the current color mode
     */
    public function getOutputDecorated()
    {
        switch ($this->colorMode()) {
            case self::COLOR_MODE_FORCED:
                return true;
            case self::COLOR_MODE_DISABLED:
                return false;
            case self::COLOR_MODE_AUTO:
            default:
                return $this->outputIsPiped() ? false : null;
        }
    }

    /**
     * Get the interactive setting for shell input.
     */
    public function getInputInteractive(): bool
    {
        switch ($this->interactiveMode()) {
            case self::INTERACTIVE_MODE_FORCED:
                return true;
            case self::INTERACTIVE_MODE_DISABLED:
                return false;
            case self::INTERACTIVE_MODE_AUTO:
            default:
                return !$this->inputIsPiped();
        }
    }

    /**
     * Set the OutputPager service.
     *
     * If a string is supplied, a ProcOutputPager will be used which shells out
     * to the specified command.
     *
     * `cat` is special-cased to use the PassthruPager directly.
     *
     * @throws \InvalidArgumentException if $pager is not a string or OutputPager instance
     *
     * @param string|OutputPager|false $pager
     */
    public function setPager($pager)
    {
        if ($pager === null || $pager === false || $pager === 'cat') {
            $pager = false;
        }

        if ($pager !== false && !\is_string($pager) && !$pager instanceof OutputPager) {
            throw new \InvalidArgumentException('Unexpected pager instance');
        }

        $this->pager = $pager;
    }

    /**
     * Get an OutputPager instance or a command for an external Proc pager.
     *
     * If no Pager has been explicitly provided, and Pcntl is available, this
     * will default to `cli.pager` ini value, falling back to `which less`.
     *
     * @return string|OutputPager|false
     */
    public function getPager()
    {
        if (!isset($this->pager) && $this->usePcntl()) {
            if (\getenv('TERM') === 'dumb') {
                return false;
            }

            if ($pager = \ini_get('cli.pager')) {
                // use the default pager
                $this->pager = $pager;
            } elseif ($less = $this->configPaths->which('less')) {
                // check for the presence of less...

                // n.b. The busybox less implementation is a bit broken, so
                // let's not use it by default.
                //
                // See https://github.com/bobthecow/psysh/issues/778
                if (@\is_link($less)) {
                    $link = @\readlink($less);
                    if ($link !== false && \strpos($link, 'busybox') !== false) {
                        return false;
                    }
                }

                $this->pager = $less.' -R -F -X';
            }
        }

        return $this->pager;
    }

    /**
     * Set the Shell AutoCompleter service.
     *
     * @param AutoCompleter $autoCompleter
     */
    public function setAutoCompleter(AutoCompleter $autoCompleter)
    {
        $this->autoCompleter = $autoCompleter;
    }

    /**
     * Get an AutoCompleter service instance.
     */
    public function getAutoCompleter(): AutoCompleter
    {
        if (!isset($this->autoCompleter)) {
            $this->autoCompleter = new AutoCompleter();
        }

        return $this->autoCompleter;
    }

    /**
     * @deprecated Nothing should be using this anymore
     */
    public function getTabCompletionMatchers(): array
    {
        @\trigger_error('`getTabCompletionMatchers` is no longer used.', \E_USER_DEPRECATED);

        return [];
    }

    /**
     * Add tab completion matchers to the AutoCompleter.
     *
     * This will buffer new matchers in the event that the Shell has not yet
     * been instantiated. This allows the user to specify matchers in their
     * config rc file, despite the fact that their file is needed in the Shell
     * constructor.
     *
     * @param array $matchers
     */
    public function addMatchers(array $matchers)
    {
        $this->newMatchers = \array_merge($this->newMatchers, $matchers);
        if (isset($this->shell)) {
            $this->doAddMatchers();
        }
    }

    /**
     * Internal method for adding tab completion matchers. This will set any new
     * matchers once a Shell is available.
     */
    private function doAddMatchers()
    {
        if (!empty($this->newMatchers)) {
            $this->shell->addMatchers($this->newMatchers);
            $this->newMatchers = [];
        }
    }

    /**
     * Configure autoload warming.
     *
     * @param bool|array $config False to disable, true for defaults, or array for custom config
     */
    public function setWarmAutoload($config): void
    {
        if (!\is_bool($config) && !\is_array($config)) {
            throw new \InvalidArgumentException('warmAutoload must be a boolean or configuration array');
        }

        // Parse and store warmers immediately
        $this->autoloadWarmers = $this->parseWarmAutoloadConfig($config);
    }

    /**
     * Get configured autoload warmers.
     *
     * If no warmers are explicitly configured, returns a default ComposerAutoloadWarmer
     * with smart settings that work for most projects.
     *
     * To disable autoload warming, set 'warmAutoload' to false.
     *
     * @return TabCompletion\AutoloadWarmer\AutoloadWarmerInterface[]
     */
    public function getAutoloadWarmers(): array
    {
        if ($this->autoloadWarmers === null) {
            $this->autoloadWarmers = $this->parseWarmAutoloadConfig(false);
        }

        return $this->autoloadWarmers;
    }

    /**
     * Parse warmAutoload configuration into autoload warmers.
     *
     * Accepts three types of configuration:
     * - true: Enable with default ComposerAutoloadWarmer
     * - false: Disable warming entirely (default)
     * - array: Custom configuration for ComposerAutoloadWarmer and/or custom warmers
     *
     * When a config array is provided:
     * - Empty array [] disables warming
     * - 'warmers' key provides custom warmer instances
     * - Other keys configure a ComposerAutoloadWarmer (implicitly enables)
     * - Both can be combined: custom warmers + configured ComposerAutoloadWarmer
     *
     * @param bool|array $config Configuration value
     *
     * @return TabCompletion\AutoloadWarmer\AutoloadWarmerInterface[]
     */
    private function parseWarmAutoloadConfig($config): array
    {
        // false = disable entirely
        if ($config === false) {
            return [];
        }

        // true = use default ComposerAutoloadWarmer
        if ($config === true) {
            return [new TabCompletion\AutoloadWarmer\ComposerAutoloadWarmer()];
        }

        // array = custom configuration
        if (!\is_array($config)) {
            throw new \InvalidArgumentException('warmAutoload must be a boolean or configuration array');
        }

        $warmers = [];

        // Extract explicit warmers if provided
        if (isset($config['warmers'])) {
            $explicitWarmers = $config['warmers'];
            if (!\is_array($explicitWarmers)) {
                throw new \InvalidArgumentException('warmAutoload[\'warmers\'] must be an array');
            }

            foreach ($explicitWarmers as $warmer) {
                if (!$warmer instanceof TabCompletion\AutoloadWarmer\AutoloadWarmerInterface) {
                    throw new \InvalidArgumentException('Autoload warmers must implement AutoloadWarmerInterface');
                }
                $warmers[] = $warmer;
            }

            unset($config['warmers']);
        }

        // If there are remaining config options, create a ComposerAutoloadWarmer with them
        if (!empty($config)) {
            $warmers[] = new TabCompletion\AutoloadWarmer\ComposerAutoloadWarmer($config);
        }

        return $warmers;
    }

    /**
     * Set implicit use statement configuration.
     *
     * Automatically adds use statements for unqualified class references when
     * a single, non-ambiguous match is found among currently defined classes,
     * interfaces, and traits within the configured namespaces.
     *
     * Works great with autoload warming (--warm-autoload) to pre-load classes
     * for better resolution. Also works with dynamically defined classes.
     *
     * Examples:
     *
     *     // Disable implicit use (default)
     *     $config->setImplicitUse(false);
     *
     *     // Enable for specific namespaces
     *     $config->setImplicitUse([
     *         'includeNamespaces' => ['App\\', 'Domain\\'],
     *     ]);
     *
     *     // Enable with exclusions
     *     $config->setImplicitUse([
     *         'includeNamespaces' => ['App\\'],
     *         'excludeNamespaces' => ['App\\Legacy\\'],
     *     ]);
     *
     * Note: At least one of includeNamespaces or excludeNamespaces must be provided.
     * If neither is provided, implicit use effectively does nothing.
     *
     * @param false|array $config False to disable, or array with includeNamespaces/excludeNamespaces
     */
    public function setImplicitUse($config): void
    {
        if ($config === false) {
            $this->implicitUse = false;

            return;
        }

        if (!\is_array($config)) {
            throw new \InvalidArgumentException('implicitUse must be false or a configuration array with includeNamespaces and/or excludeNamespaces');
        }

        $this->implicitUse = $config;
    }

    /**
     * Get implicit use configuration.
     *
     * @return bool|array Implicit use configuration
     */
    public function getImplicitUse()
    {
        return $this->implicitUse;
    }

    /**
     * Configure logging.
     *
     * Logs PsySH input, commands, and executed code to the provided logger.
     * Accepts a PSR-3 logger, a simple callback, or an array for more control
     * over log levels.
     *
     * Examples:
     *
     *     // Simple callback logging
     *     $config->setLogging(function ($kind, $data) {
     *         $line = sprintf("[%s] %s\n", $kind, $data);
     *         file_put_contents('/tmp/psysh.log', $line, FILE_APPEND);
     *     });
     *
     *     // PSR-3 logger with defaults (input=info, command=info, execute=debug)
     *     $config->setLogging($psrLogger);
     *
     *     // Set single level for all event types
     *     $config->setLogging([
     *         'logger' => $psrLogger,
     *         'level' => 'debug',
     *     ]);
     *
     *     // Granular control over each event type
     *     $config->setLogging([
     *         'logger' => $psrLogger,
     *         'level' => [
     *             'input'   => 'info',
     *             'command' => false, // disable logging
     *             'execute' => 'debug',
     *         ],
     *     ]);
     *
     * @param \Psr\Log\LoggerInterface|callable|array $logging
     */
    public function setLogging($logging): void
    {
        $this->logger = $this->parseLoggingConfig($logging);
    }

    /**
     * Get a ShellLogger instance if logging is configured.
     *
     * @return ShellLogger|null
     */
    public function getLogger(): ?ShellLogger
    {
        return $this->logger;
    }

    /**
     * Get an InputLoggingListener if input logging is enabled.
     *
     * @return InputLoggingListener|null
     */
    public function getInputLogger(): ?InputLoggingListener
    {
        $logger = $this->getLogger();
        if ($logger === null || $logger->isInputDisabled()) {
            return null;
        }

        return new InputLoggingListener($logger);
    }

    /**
     * Get an ExecutionLoggingListener if execution logging is enabled.
     *
     * @return ExecutionLoggingListener|null
     */
    public function getExecutionLogger(): ?ExecutionLoggingListener
    {
        $logger = $this->getLogger();
        if ($logger === null || $logger->isExecuteDisabled()) {
            return null;
        }

        return new ExecutionLoggingListener($logger);
    }

    /**
     * Parse logging configuration.
     *
     * @param \Psr\Log\LoggerInterface|Logger\CallbackLogger|callable|array $config
     *
     * @return ShellLogger
     */
    private function parseLoggingConfig($config): ShellLogger
    {
        if (!\is_array($config)) {
            $config = ['logger' => $config];
        }

        if (!isset($config['logger'])) {
            throw new \InvalidArgumentException('Logging config array must include a "logger" key');
        }

        $logger = $config['logger'];

        if (\is_callable($logger)) {
            $logger = new CallbackLogger($logger);
        }

        if (!$this->isLogger($logger)) {
            throw new \InvalidArgumentException('Logging "logger" must be a logger instance or callable');
        }

        $defaults = [
            'input'   => 'info',
            'command' => 'info',
            'execute' => 'debug',
        ];

        if (isset($config['level'])) {
            $level = $config['level'];

            // String: apply same level to all types
            if (\is_string($level)) {
                $levels = [
                    'input'   => $level,
                    'command' => $level,
                    'execute' => $level,
                ];
            } elseif (\is_array($level)) {
                // Array: granular per-type levels
                $levels = [
                    'input'   => $level['input'] ?? $defaults['input'],
                    'command' => $level['command'] ?? $defaults['command'],
                    'execute' => $level['execute'] ?? $defaults['execute'],
                ];
            } else {
                throw new \InvalidArgumentException('Logging "level" must be a string or array');
            }
        } else {
            $levels = $defaults;
        }

        return new ShellLogger($logger, $levels);
    }

    /**
     * Check if a value is a valid logger instance.
     *
     * @param mixed $logger
     *
     * @return bool
     */
    private function isLogger($logger): bool
    {
        if ($logger instanceof CallbackLogger) {
            return true;
        }

        // Safe check for LoggerInterface without requiring psr/log as a dependency
        return \interface_exists('Psr\Log\LoggerInterface') && $logger instanceof \Psr\Log\LoggerInterface;
    }

    /**
     * @deprecated Use `addMatchers` instead
     *
     * @param array $matchers
     */
    public function addTabCompletionMatchers(array $matchers)
    {
        @\trigger_error('`addTabCompletionMatchers` is deprecated; call `addMatchers` instead.', \E_USER_DEPRECATED);

        $this->addMatchers($matchers);
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
        $this->newCommands = \array_merge($this->newCommands, $commands);
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
            $this->newCommands = [];
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
        $this->doAddMatchers();

        // Configure SignatureFormatter for hyperlinks
        SignatureFormatter::setManual($this->getManual());
    }

    /**
     * Set the PHP manual database file.
     *
     * This file should be an SQLite database generated from the phpdoc source
     * with the `bin/build_manual` script.
     *
     * @param string $filename
     */
    public function setManualDbFile(string $filename)
    {
        $this->manualDbFile = (string) $filename;

        // Reconfigure SignatureFormatter with new manual database
        try {
            SignatureFormatter::setManual($this->getManual());
        } catch (InvalidManualException $e) {
            // Show user-friendly error for invalid explicitly configured manual
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the current PHP manual database file.
     *
     * Searches for manual files in order of preference:
     *  1. php_manual.php (v3 format)
     *  2. php_manual.sqlite (v2 format, legacy)
     *
     * @return string|null Default: '~/.local/share/psysh/php_manual.*'
     */
    public function getManualDbFile()
    {
        if (isset($this->manualDbFile)) {
            return $this->manualDbFile;
        }

        // Prefer v3 format over v2
        $files = $this->configPaths->dataFiles(['php_manual.php', 'php_manual.sqlite']);
        if (!empty($files)) {
            if ($this->warnOnMultipleConfigs && \count($files) > 1) {
                $prettyFiles = \array_map([ConfigPaths::class, 'prettyPath'], $files);
                $msg = \sprintf('Multiple manual database files found: %s. Using %s', \implode(', ', $prettyFiles), $prettyFiles[0]);
                \trigger_error($msg, \E_USER_NOTICE);
            }

            return $this->manualDbFile = $files[0];
        }

        return null;
    }

    /**
     * Get a PHP manual database connection.
     *
     * @deprecated Use getManual() instead for unified access to all manual formats
     *
     * @return \PDO|null
     */
    public function getManualDb()
    {
        if (!isset($this->manualDb)) {
            $dbFile = $this->getManualDbFile();
            if ($dbFile !== null && \is_file($dbFile) && \substr($dbFile, -7) === '.sqlite') {
                try {
                    $this->manualDb = new \PDO('sqlite:'.$dbFile);

                    // Validate the database has the required structure
                    $result = $this->manualDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name='php_manual'");
                    if ($result === false || $result->fetchColumn() === false) {
                        throw new InvalidManualException('Manual database is missing required tables', $dbFile);
                    }
                } catch (\PDOException $e) {
                    if ($e->getMessage() === 'could not find driver') {
                        throw new RuntimeException('SQLite PDO driver not found', 0, $e);
                    } else {
                        throw new InvalidManualException('Invalid SQLite manual database: '.$e->getMessage(), $dbFile, 0, $e);
                    }
                }
            }
        }

        return $this->manualDb;
    }

    /**
     * Get a PHP manual instance.
     *
     * Automatically detects the manual format and returns the appropriate manual type.
     * Supports v2 (SQLite) and v3 (PHP) formats.
     *
     * @return ManualInterface|null
     */
    public function getManual()
    {
        if (!isset($this->manual)) {
            $this->manual = $this->loadManual();
        }

        return $this->manual;
    }

    /**
     * Load manual from filesystem or bundled Phar, preferring newest English version.
     *
     * Priority:
     *  1. Explicit config: if user configured a specific file, use it
     *  2. Local non-English: user downloaded a specific language
     *  3. Newest English: compare local vs bundled
     *
     * @return ManualInterface|null
     */
    private function loadManual()
    {
        // Priority 1: If user explicitly configured a manual file, use it
        if (isset($this->manualDbFile)) {
            $manual = $this->loadManualFromFile($this->manualDbFile);
            if ($manual !== null) {
                return $manual;
            }
        }

        // Check filesystem locations (auto-discovered)
        $localFile = $this->getManualDbFile();
        $localManual = null;
        $localMeta = null;

        if ($localFile !== null && \is_file($localFile)) {
            try {
                $localManual = $this->loadManualFromFile($localFile);
                if ($localManual !== null) {
                    $localMeta = $localManual->getMeta();
                }
            } catch (InvalidManualException $e) {
                // Auto-discovered file is invalid - fall back to bundled
            }
        }

        // Check bundled manual in Phar
        $bundledManual = null;
        $bundledMeta = null;

        if (\Phar::running(false)) {
            $bundledFile = 'phar://'.\Phar::running(false).'/php_manual.php';
            if (\is_file($bundledFile)) {
                $bundledManual = $this->loadManualFromFile($bundledFile);
                if ($bundledManual !== null) {
                    $bundledMeta = $bundledManual->getMeta();
                }
            }
        }

        // Priority 2: If local exists and is not English, use local (user wants that language)
        // Priority 3: Otherwise, use newest English (compare local vs bundled)

        if ($localManual !== null) {
            $localLang = $localMeta['lang'] ?? 'en';

            // Non-English local manual takes priority
            if ($localLang !== 'en') {
                return $localManual;
            }

            // Both are English, pick newest
            $localTimestamp = $localMeta['built_at'] ?? 0;
            $bundledTimestamp = $bundledMeta['built_at'] ?? 0;

            if ($localTimestamp >= $bundledTimestamp) {
                return $localManual;
            } else {
                return $bundledManual;
            }
        }

        // No local manual, use bundled if available
        return $bundledManual;
    }

    /**
     * Load a manual from a file path.
     *
     * @param string $file
     *
     * @return ManualInterface|null
     *
     * @throws InvalidManualException if manual file is invalid
     */
    private function loadManualFromFile(string $file)
    {
        // Detect format by extension
        if (\substr($file, -4) === '.php') {
            return new V3Manual($file);
        } elseif (\substr($file, -7) === '.sqlite') {
            // Legacy v2 format
            if ($db = $this->getManualDb()) {
                return new V2Manual($db);
            }
        }

        return null;
    }

    /**
     * Add an array of casters definitions.
     *
     * @param array $casters
     */
    public function addCasters(array $casters)
    {
        $this->getPresenter()->addCasters($casters);
    }

    /**
     * Get the Presenter service.
     */
    public function getPresenter(): Presenter
    {
        if (!isset($this->presenter)) {
            $this->presenter = new Presenter($this->getOutput()->getFormatter(), $this->forceArrayIndexes());
        }

        return $this->presenter;
    }

    /**
     * Enable or disable warnings on multiple configuration or data files.
     *
     * @see self::warnOnMultipleConfigs()
     *
     * @param bool $warnOnMultipleConfigs
     */
    public function setWarnOnMultipleConfigs(bool $warnOnMultipleConfigs)
    {
        $this->warnOnMultipleConfigs = (bool) $warnOnMultipleConfigs;
    }

    /**
     * Check whether to warn on multiple configuration or data files.
     *
     * By default, PsySH will use the file with highest precedence, and will
     * silently ignore all others. With this enabled, a warning will be emitted
     * (but not an exception thrown) if multiple configuration or data files
     * are found.
     *
     * This will default to true in a future release, but is false for now.
     */
    public function warnOnMultipleConfigs(): bool
    {
        return $this->warnOnMultipleConfigs;
    }

    /**
     * Set the current color mode.
     *
     * @throws \InvalidArgumentException if the color mode isn't auto, forced or disabled
     *
     * @param string $colorMode
     */
    public function setColorMode(string $colorMode)
    {
        $validColorModes = [
            self::COLOR_MODE_AUTO,
            self::COLOR_MODE_FORCED,
            self::COLOR_MODE_DISABLED,
        ];

        if (!\in_array($colorMode, $validColorModes)) {
            throw new \InvalidArgumentException('Invalid color mode: '.$colorMode);
        }

        $this->colorMode = $colorMode;
    }

    /**
     * Get the current color mode.
     */
    public function colorMode(): string
    {
        return $this->colorMode;
    }

    /**
     * Set the shell's interactive mode.
     *
     * @throws \InvalidArgumentException if interactive mode isn't disabled, forced, or auto
     *
     * @param string $interactiveMode
     */
    public function setInteractiveMode(string $interactiveMode)
    {
        $validInteractiveModes = [
            self::INTERACTIVE_MODE_AUTO,
            self::INTERACTIVE_MODE_FORCED,
            self::INTERACTIVE_MODE_DISABLED,
        ];

        if (!\in_array($interactiveMode, $validInteractiveModes)) {
            throw new \InvalidArgumentException('Invalid interactive mode: '.$interactiveMode);
        }

        $this->interactiveMode = $interactiveMode;
    }

    /**
     * Get the current interactive mode.
     */
    public function interactiveMode(): string
    {
        return $this->interactiveMode;
    }

    /**
     * Set an update checker service instance.
     *
     * @param Checker $checker
     */
    public function setChecker(Checker $checker)
    {
        $this->checker = $checker;
    }

    /**
     * Get an update checker service instance.
     *
     * If none has been explicitly defined, this will create a new instance.
     */
    public function getChecker(): Checker
    {
        if (!isset($this->checker)) {
            $interval = $this->getUpdateCheck();
            switch ($interval) {
                case Checker::ALWAYS:
                    $this->checker = new GitHubChecker();
                    break;

                case Checker::DAILY:
                case Checker::WEEKLY:
                case Checker::MONTHLY:
                    $checkFile = $this->getUpdateCheckCacheFile();
                    if ($checkFile === false) {
                        $this->checker = new NoopChecker();
                    } else {
                        $this->checker = new IntervalChecker($checkFile, $interval);
                    }
                    break;

                case Checker::NEVER:
                    $this->checker = new NoopChecker();
                    break;
            }
        }

        return $this->checker;
    }

    /**
     * Get the current update check interval.
     *
     * One of 'always', 'daily', 'weekly', 'monthly' or 'never'. If none is
     * explicitly set, default to 'weekly'.
     */
    public function getUpdateCheck(): string
    {
        return isset($this->updateCheck) ? $this->updateCheck : Checker::WEEKLY;
    }

    /**
     * Set the update check interval.
     *
     * @throws \InvalidArgumentException if the update check interval is unknown
     *
     * @param string $interval
     */
    public function setUpdateCheck(string $interval)
    {
        $validIntervals = [
            Checker::ALWAYS,
            Checker::DAILY,
            Checker::WEEKLY,
            Checker::MONTHLY,
            Checker::NEVER,
        ];

        if (!\in_array($interval, $validIntervals)) {
            throw new \InvalidArgumentException('Invalid update check interval: '.$interval);
        }

        $this->updateCheck = $interval;
    }

    /**
     * Get a cache file path for the update checker.
     *
     * @return string|false Return false if config file/directory is not writable
     */
    public function getUpdateCheckCacheFile()
    {
        $configDir = $this->configPaths->currentConfigDir();
        if ($configDir === null) {
            return false;
        }

        return ConfigPaths::touchFileWithMkdir($configDir.'/update_check.json');
    }

    /**
     * Get the current manual update check interval.
     *
     * One of 'always', 'daily', 'weekly', 'monthly' or 'never'. If none is
     * explicitly set, default to 'weekly'.
     */
    public function getUpdateManualCheck(): string
    {
        return isset($this->updateManualCheck) ? $this->updateManualCheck : ManualUpdater\Checker::WEEKLY;
    }

    /**
     * Set the manual update check interval.
     *
     * @throws \InvalidArgumentException if the update check interval is unknown
     *
     * @param string $interval
     */
    public function setUpdateManualCheck(string $interval)
    {
        $validIntervals = [
            ManualUpdater\Checker::ALWAYS,
            ManualUpdater\Checker::DAILY,
            ManualUpdater\Checker::WEEKLY,
            ManualUpdater\Checker::MONTHLY,
            ManualUpdater\Checker::NEVER,
        ];

        if (!\in_array($interval, $validIntervals)) {
            throw new \InvalidArgumentException('Invalid manual update check interval: '.$interval);
        }

        $this->updateManualCheck = $interval;
    }

    /**
     * Get a manual update checker.
     *
     * If none has been explicitly defined, this will create a new instance.
     *
     * @param string|null $lang   Override language (otherwise uses current manual's language or 'en')
     * @param bool        $always Force immediate check, ignoring interval setting
     *
     * @return ManualUpdater\Checker|null
     */
    public function getManualChecker(?string $lang = null, bool $always = false): ?ManualUpdater\Checker
    {
        // Get current manual info
        $manualFile = $this->getManualDbFile();
        $currentMeta = null;
        if ($manualFile && \file_exists($manualFile)) {
            $manual = $this->getManual();
            if ($manual) {
                $currentMeta = $manual->getMeta();
            }
        }

        $currentVersion = $currentMeta['version'] ?? null;
        $currentLang = $currentMeta['lang'] ?? null;

        // Determine language (priority: explicit param, current manual, default to English)
        if ($lang === null) {
            $lang = $currentLang ?? 'en';
        }

        // Determine format from current manual file extension, default to v3
        $format = 'php';
        if ($manualFile && \substr($manualFile, -7) === '.sqlite') {
            $format = 'sqlite';
        }

        $interval = $always ? ManualUpdater\Checker::ALWAYS : $this->getUpdateManualCheck();
        switch ($interval) {
            case ManualUpdater\Checker::ALWAYS:
                return new ManualUpdater\GitHubChecker($lang, $format, $currentVersion, $currentLang);

            case ManualUpdater\Checker::DAILY:
            case ManualUpdater\Checker::WEEKLY:
            case ManualUpdater\Checker::MONTHLY:
                $checkFile = $this->getManualUpdateCheckCacheFile();
                if ($checkFile === false) {
                    return null; // No writable cache file
                }

                $baseChecker = new ManualUpdater\GitHubChecker($lang, $format, $currentVersion, $currentLang);

                return new ManualUpdater\IntervalChecker($baseChecker, $checkFile, $interval);

            case ManualUpdater\Checker::NEVER:
            default:
                return null;
        }
    }

    /**
     * Get a cache file path for the manual update checker.
     *
     * @return string|false Return false if config file/directory is not writable
     */
    public function getManualUpdateCheckCacheFile()
    {
        $configDir = $this->configPaths->currentConfigDir();
        if ($configDir === null) {
            return false;
        }

        return ConfigPaths::touchFileWithMkdir($configDir.'/manual_update_check.json');
    }

    /**
     * Get the manual installation directory path.
     *
     * @return string|false Return false if data directory is not writable
     */
    public function getManualInstallDir()
    {
        $dataDir = $this->configPaths->currentDataDir();
        if ($dataDir === null) {
            return false;
        }

        if (!ConfigPaths::ensureDir($dataDir)) {
            return false;
        }

        return $dataDir;
    }

    /**
     * Set the startup message.
     *
     * @param string $message
     */
    public function setStartupMessage(string $message)
    {
        $this->startupMessage = $message;
    }

    /**
     * Get the startup message.
     *
     * @return string|null
     */
    public function getStartupMessage()
    {
        return $this->startupMessage;
    }

    /**
     * Set the prompt.
     *
     * @deprecated The `prompt` configuration has been replaced by Themes and support will
     * eventually be removed. In the meantime, prompt is applied first by the Theme, then overridden
     * by any explicitly defined prompt.
     *
     * Note that providing a prompt but not a theme config will implicitly use the `classic` theme.
     */
    public function setPrompt(string $prompt)
    {
        $this->prompt = $prompt;

        if (isset($this->theme)) {
            $this->theme->setPrompt($prompt);
        }
    }

    /**
     * Get the prompt.
     *
     * @return string|null
     */
    public function getPrompt()
    {
        return $this->prompt;
    }

    /**
     * Get the force array indexes.
     */
    public function forceArrayIndexes(): bool
    {
        return $this->forceArrayIndexes;
    }

    /**
     * Set the force array indexes.
     *
     * @param bool $forceArrayIndexes
     */
    public function setForceArrayIndexes(bool $forceArrayIndexes)
    {
        $this->forceArrayIndexes = $forceArrayIndexes;
    }

    /**
     * Set the current output Theme.
     *
     * @param Theme|string|array $theme Theme (or Theme config)
     */
    public function setTheme($theme)
    {
        if (!$theme instanceof Theme) {
            $theme = new Theme($theme);
        }

        $this->theme = $theme;

        if (isset($this->prompt)) {
            $this->theme->setPrompt($this->prompt);
        }

        if (isset($this->output)) {
            $this->output->setTheme($theme);
            $this->applyFormatterStyles();
        }
    }

    /**
     * Get the current output Theme.
     */
    public function theme(): Theme
    {
        if (!isset($this->theme)) {
            // If a prompt is explicitly set, and a theme is not, base it on the `classic` theme.
            $this->theme = $this->prompt ? new Theme('classic') : new Theme();
        }

        if (isset($this->prompt)) {
            $this->theme->setPrompt($this->prompt);
        }

        return $this->theme;
    }

    /**
     * Set the shell output formatter styles.
     *
     * Accepts a map from style name to [fg, bg, options], for example:
     *
     *     [
     *         'error' => ['white', 'red', ['bold']],
     *         'warning' => ['black', 'yellow'],
     *     ]
     *
     * Foreground, background or options can be null, or even omitted entirely.
     *
     * @deprecated The `formatterStyles` configuration has been replaced by Themes and support will
     * eventually be removed. In the meantime, styles are applied first by the Theme, then
     * overridden by any explicitly defined formatter styles.
     */
    public function setFormatterStyles(array $formatterStyles)
    {
        foreach ($formatterStyles as $name => $style) {
            $this->formatterStyles[$name] = new OutputFormatterStyle(...$style);
        }

        if (isset($this->output)) {
            $this->applyFormatterStyles();
        }
    }

    /**
     * Internal method for applying output formatter style customization.
     *
     * This is called on initialization of the shell output, and again if the
     * formatter styles config is updated.
     *
     * @deprecated The `formatterStyles` configuration has been replaced by Themes and support will
     * eventually be removed. In the meantime, styles are applied first by the Theme, then
     * overridden by any explicitly defined formatter styles.
     */
    private function applyFormatterStyles()
    {
        $formatter = $this->output->getFormatter();
        foreach ($this->formatterStyles as $name => $style) {
            $formatter->setStyle($name, $style);
        }

        $errorFormatter = $this->output->getErrorOutput()->getFormatter();
        foreach (Theme::ERROR_STYLES as $name) {
            if (isset($this->formatterStyles[$name])) {
                $errorFormatter->setStyle($name, $this->formatterStyles[$name]);
            }
        }
    }

    /**
     * Get the configured output verbosity.
     */
    public function verbosity(): string
    {
        return $this->verbosity;
    }

    /**
     * Set the shell output verbosity.
     *
     * Accepts OutputInterface verbosity constants.
     *
     * @throws \InvalidArgumentException if verbosity level is invalid
     *
     * @param string $verbosity
     */
    public function setVerbosity(string $verbosity)
    {
        $validVerbosityLevels = [
            self::VERBOSITY_QUIET,
            self::VERBOSITY_NORMAL,
            self::VERBOSITY_VERBOSE,
            self::VERBOSITY_VERY_VERBOSE,
            self::VERBOSITY_DEBUG,
        ];

        if (!\in_array($verbosity, $validVerbosityLevels)) {
            throw new \InvalidArgumentException('Invalid verbosity level: '.$verbosity);
        }

        $this->verbosity = $verbosity;

        if (isset($this->output)) {
            $this->output->setVerbosity($this->getOutputVerbosity());
        }
    }

    /**
     * Map the verbosity configuration to OutputInterface verbosity constants.
     *
     * @return int OutputInterface verbosity level
     */
    public function getOutputVerbosity(): int
    {
        switch ($this->verbosity()) {
            case self::VERBOSITY_QUIET:
                return OutputInterface::VERBOSITY_QUIET;
            case self::VERBOSITY_VERBOSE:
                return OutputInterface::VERBOSITY_VERBOSE;
            case self::VERBOSITY_VERY_VERBOSE:
                return OutputInterface::VERBOSITY_VERY_VERBOSE;
            case self::VERBOSITY_DEBUG:
                return OutputInterface::VERBOSITY_DEBUG;
            case self::VERBOSITY_NORMAL:
            default:
                return OutputInterface::VERBOSITY_NORMAL;
        }
    }

    /**
     * Guess whether stdin is piped.
     *
     * This is mostly useful for deciding whether to use non-interactive mode.
     */
    public function inputIsPiped(): bool
    {
        if ($this->pipedInput === null) {
            $this->pipedInput = \defined('STDIN') && self::looksLikeAPipe(\STDIN);
        }

        return $this->pipedInput;
    }

    /**
     * Guess whether shell output is piped.
     *
     * This is mostly useful for deciding whether to use non-decorated output.
     */
    public function outputIsPiped(): bool
    {
        if ($this->pipedOutput === null) {
            $this->pipedOutput = self::looksLikeAPipe($this->getOutput()->getStream());
        }

        return $this->pipedOutput;
    }

    /**
     * Guess whether an input or output stream is piped.
     *
     * @param resource|int $stream
     */
    private static function looksLikeAPipe($stream): bool
    {
        if (\function_exists('posix_isatty')) {
            return !\posix_isatty($stream);
        }

        $stat = \fstat($stream);
        $mode = $stat['mode'] & 0170000;

        return $mode === 0010000 || $mode === 0040000 || $mode === 0100000 || $mode === 0120000;
    }
}
