<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2023 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\ExecutionLoop\ProcessForker;
use Psy\VersionUpdater\GitHubChecker;
use Psy\VersionUpdater\Installer;
use Psy\VersionUpdater\SelfUpdate;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

if (!\function_exists('Psy\\sh')) {
    /**
     * Command to return the eval-able code to startup PsySH.
     *
     *     eval(\Psy\sh());
     */
    function sh(): string
    {
        if (\version_compare(\PHP_VERSION, '8.0', '<')) {
            return '\extract(\Psy\debug(\get_defined_vars(), isset($this) ? $this : @\get_called_class()));';
        }

        return <<<'EOS'
if (isset($this)) {
    \extract(\Psy\debug(\get_defined_vars(), $this));
} else {
    try {
        static::class;
        \extract(\Psy\debug(\get_defined_vars(), static::class));
    } catch (\Error $e) {
        \extract(\Psy\debug(\get_defined_vars()));
    }
}
EOS;
    }
}

if (!\function_exists('Psy\\debug')) {
    /**
     * Invoke a Psy Shell from the current context.
     *
     * For example:
     *
     *     foreach ($items as $item) {
     *         \Psy\debug(get_defined_vars());
     *     }
     *
     * If you would like your shell interaction to affect the state of the
     * current context, you can extract() the values returned from this call:
     *
     *     foreach ($items as $item) {
     *         extract(\Psy\debug(get_defined_vars()));
     *         var_dump($item); // will be whatever you set $item to in Psy Shell
     *     }
     *
     * Optionally, supply an object as the `$bindTo` parameter. This determines
     * the value `$this` will have in the shell, and sets up class scope so that
     * private and protected members are accessible:
     *
     *     class Foo {
     *         function bar() {
     *             \Psy\debug(get_defined_vars(), $this);
     *         }
     *     }
     *
     * For the static equivalent, pass a class name as the `$bindTo` parameter.
     * This makes `self` work in the shell, and sets up static scope so that
     * private and protected static members are accessible:
     *
     *     class Foo {
     *         static function bar() {
     *             \Psy\debug(get_defined_vars(), get_called_class());
     *         }
     *     }
     *
     * @param array         $vars   Scope variables from the calling context (default: [])
     * @param object|string $bindTo Bound object ($this) or class (self) value for the shell
     *
     * @return array Scope variables from the debugger session
     */
    function debug(array $vars = [], $bindTo = null): array
    {
        echo \PHP_EOL;

        $sh = new Shell();
        $sh->setScopeVariables($vars);

        // Show a couple of lines of call context for the debug session.
        //
        // @todo come up with a better way of doing this which doesn't involve injecting input :-P
        if ($sh->has('whereami')) {
            $sh->addInput('whereami -n2', true);
        }

        if (\is_string($bindTo)) {
            $sh->setBoundClass($bindTo);
        } elseif ($bindTo !== null) {
            $sh->setBoundObject($bindTo);
        }

        $sh->run();

        return $sh->getScopeVariables(false);
    }
}

if (!\function_exists('Psy\\info')) {
    /**
     * Get a bunch of debugging info about the current PsySH environment and
     * configuration.
     *
     * If a Configuration param is passed, that configuration is stored and
     * used for the current shell session, and no debugging info is returned.
     *
     * @param Configuration|null $config
     *
     * @return array|null
     */
    function info(Configuration $config = null)
    {
        static $lastConfig;
        if ($config !== null) {
            $lastConfig = $config;

            return;
        }

        $prettyPath = function ($path) {
            return $path;
        };

        $homeDir = (new ConfigPaths())->homeDir();
        if ($homeDir && $homeDir = \rtrim($homeDir, '/')) {
            $homePattern = '#^'.\preg_quote($homeDir, '#').'/#';
            $prettyPath = function ($path) use ($homePattern) {
                if (\is_string($path)) {
                    return \preg_replace($homePattern, '~/', $path);
                } else {
                    return $path;
                }
            };
        }

        $config = $lastConfig ?: new Configuration();
        $configEnv = (isset($_SERVER['PSYSH_CONFIG']) && $_SERVER['PSYSH_CONFIG']) ? $_SERVER['PSYSH_CONFIG'] : false;
        if ($configEnv === false && \PHP_SAPI === 'cli-server') {
            $configEnv = \getenv('PSYSH_CONFIG');
        }

        $shellInfo = [
            'PsySH version' => Shell::VERSION,
        ];

        $core = [
            'PHP version'         => \PHP_VERSION,
            'OS'                  => \PHP_OS,
            'default includes'    => $config->getDefaultIncludes(),
            'require semicolons'  => $config->requireSemicolons(),
            'error logging level' => $config->errorLoggingLevel(),
            'config file'         => [
                'default config file' => $prettyPath($config->getConfigFile()),
                'local config file'   => $prettyPath($config->getLocalConfigFile()),
                'PSYSH_CONFIG env'    => $prettyPath($configEnv),
            ],
            // 'config dir'  => $config->getConfigDir(),
            // 'data dir'    => $config->getDataDir(),
            // 'runtime dir' => $config->getRuntimeDir(),
        ];

        // Use an explicit, fresh update check here, rather than relying on whatever is in $config.
        $checker = new GitHubChecker();
        $updateAvailable = null;
        $latest = null;
        try {
            $updateAvailable = !$checker->isLatest();
            $latest = $checker->getLatest();
        } catch (\Throwable $e) {
        }

        $updates = [
            'update available'       => $updateAvailable,
            'latest release version' => $latest,
            'update check interval'  => $config->getUpdateCheck(),
            'update cache file'      => $prettyPath($config->getUpdateCheckCacheFile()),
        ];

        $input = [
            'interactive mode'  => $config->interactiveMode(),
            'input interactive' => $config->getInputInteractive(),
            'yolo'              => $config->yolo(),
        ];

        if ($config->hasReadline()) {
            $info = \readline_info();

            $readline = [
                'readline available' => true,
                'readline enabled'   => $config->useReadline(),
                'readline service'   => \get_class($config->getReadline()),
            ];

            if (isset($info['library_version'])) {
                $readline['readline library'] = $info['library_version'];
            }

            if (isset($info['readline_name']) && $info['readline_name'] !== '') {
                $readline['readline name'] = $info['readline_name'];
            }
        } else {
            $readline = [
                'readline available' => false,
            ];
        }

        $output = [
            'color mode'       => $config->colorMode(),
            'output decorated' => $config->getOutputDecorated(),
            'output verbosity' => $config->verbosity(),
            'output pager'     => $config->getPager(),
        ];

        $theme = $config->theme();
        // TODO: show styles (but only if they're different than default?)
        $output['theme'] = [
            'compact'      => $theme->compact(),
            'prompt'       => $theme->prompt(),
            'bufferPrompt' => $theme->bufferPrompt(),
            'replayPrompt' => $theme->replayPrompt(),
            'returnValue'  => $theme->returnValue(),
        ];

        $pcntl = [
            'pcntl available' => ProcessForker::isPcntlSupported(),
            'posix available' => ProcessForker::isPosixSupported(),
        ];

        if ($disabledPcntl = ProcessForker::disabledPcntlFunctions()) {
            $pcntl['disabled pcntl functions'] = $disabledPcntl;
        }

        if ($disabledPosix = ProcessForker::disabledPosixFunctions()) {
            $pcntl['disabled posix functions'] = $disabledPosix;
        }

        $pcntl['use pcntl'] = $config->usePcntl();

        $history = [
            'history file'     => $prettyPath($config->getHistoryFile()),
            'history size'     => $config->getHistorySize(),
            'erase duplicates' => $config->getEraseDuplicates(),
        ];

        $docs = [
            'manual db file'   => $prettyPath($config->getManualDbFile()),
            'sqlite available' => true,
        ];

        try {
            if ($db = $config->getManualDb()) {
                if ($q = $db->query('SELECT * FROM meta;')) {
                    $q->setFetchMode(\PDO::FETCH_KEY_PAIR);
                    $meta = $q->fetchAll();

                    foreach ($meta as $key => $val) {
                        switch ($key) {
                            case 'built_at':
                                $d = new \DateTime('@'.$val);
                                $val = $d->format(\DateTime::RFC2822);
                                break;
                        }
                        $key = 'db '.\str_replace('_', ' ', $key);
                        $docs[$key] = $val;
                    }
                } else {
                    $docs['db schema'] = '0.1.0';
                }
            }
        } catch (Exception\RuntimeException $e) {
            if ($e->getMessage() === 'SQLite PDO driver not found') {
                $docs['sqlite available'] = false;
            } else {
                throw $e;
            }
        }

        $autocomplete = [
            'tab completion enabled' => $config->useTabCompletion(),
            'bracketed paste'        => $config->useBracketedPaste(),
        ];

        // Shenanigans, but totally justified.
        try {
            if ($shell = Sudo::fetchProperty($config, 'shell')) {
                $shellClass = \get_class($shell);
                if ($shellClass !== 'Psy\\Shell') {
                    $shellInfo = [
                        'PsySH version' => $shell::VERSION,
                        'Shell class'   => $shellClass,
                    ];
                }

                try {
                    $core['loop listeners'] = \array_map('get_class', Sudo::fetchProperty($shell, 'loopListeners'));
                } catch (\ReflectionException $e) {
                    // shrug
                }

                $core['commands'] = \array_map('get_class', $shell->all());

                try {
                    $autocomplete['custom matchers'] = \array_map('get_class', Sudo::fetchProperty($shell, 'matchers'));
                } catch (\ReflectionException $e) {
                    // shrug
                }
            }
        } catch (\ReflectionException $e) {
            // shrug
        }

        // @todo Show Presenter / custom casters.

        return \array_merge($shellInfo, $core, \compact('updates', 'pcntl', 'input', 'readline', 'output', 'history', 'docs', 'autocomplete'));
    }
}

if (!\function_exists('Psy\\bin')) {
    /**
     * `psysh` command line executable.
     *
     * @return \Closure
     */
    function bin(): \Closure
    {
        return function () {
            if (!isset($_SERVER['PSYSH_IGNORE_ENV']) || !$_SERVER['PSYSH_IGNORE_ENV']) {
                if (\defined('HHVM_VERSION_ID')) {
                    \fwrite(\STDERR, 'PsySH v0.11 and higher does not support HHVM. Install an older version, or set the environment variable PSYSH_IGNORE_ENV=1 to override this restriction and proceed anyway.'.\PHP_EOL);
                    exit(1);
                }

                if (\PHP_VERSION_ID < 70000) {
                    \fwrite(\STDERR, 'PHP 7.0.0 or higher is required. You can set the environment variable PSYSH_IGNORE_ENV=1 to override this restriction and proceed anyway.'.\PHP_EOL);
                    exit(1);
                }

                if (\PHP_VERSION_ID > 89999) {
                    \fwrite(\STDERR, 'PHP 9 or higher is not supported. You can set the environment variable PSYSH_IGNORE_ENV=1 to override this restriction and proceed anyway.'.\PHP_EOL);
                    exit(1);
                }

                if (!\function_exists('json_encode')) {
                    \fwrite(\STDERR, 'The JSON extension is required. Please install it. You can set the environment variable PSYSH_IGNORE_ENV=1 to override this restriction and proceed anyway.'.\PHP_EOL);
                    exit(1);
                }

                if (!\function_exists('token_get_all')) {
                    \fwrite(\STDERR, 'The Tokenizer extension is required. Please install it. You can set the environment variable PSYSH_IGNORE_ENV=1 to override this restriction and proceed anyway.'.\PHP_EOL);
                    exit(1);
                }
            }

            $usageException = null;
            $shellIsPhar = Shell::isPhar();

            $input = new ArgvInput();
            try {
                $input->bind(new InputDefinition(\array_merge(Configuration::getInputOptions(), [
                    new InputOption('help', 'h', InputOption::VALUE_NONE),
                    new InputOption('version', 'V', InputOption::VALUE_NONE),
                    new InputOption('self-update', 'u', InputOption::VALUE_NONE),

                    new InputArgument('include', InputArgument::IS_ARRAY),
                ])));
            } catch (\RuntimeException $e) {
                $usageException = $e;
            }

            try {
                $config = Configuration::fromInput($input);
            } catch (\InvalidArgumentException $e) {
                $usageException = $e;
            }

            // Handle --help
            if ($usageException !== null || $input->getOption('help')) {
                if ($usageException !== null) {
                    echo $usageException->getMessage().\PHP_EOL.\PHP_EOL;
                }

                $version = Shell::getVersionHeader(false);
                $argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
                $name = $argv ? \basename(\reset($argv)) : 'psysh';

                echo <<<EOL
$version

Usage:
  $name [--version] [--help] [files...]

Options:
  -h, --help            Display this help message.
  -c, --config FILE     Use an alternate PsySH config file location.
      --cwd PATH        Use an alternate working directory.
  -V, --version         Display the PsySH version.

EOL;
                if ($shellIsPhar) {
                    echo <<<EOL
  -u, --self-update     Install a newer version if available.

EOL;
                }
                echo <<<EOL
      --color           Force colors in output.
      --no-color        Disable colors in output.
  -i, --interactive     Force PsySH to run in interactive mode.
  -n, --no-interactive  Run PsySH without interactive input. Requires input from stdin.
  -r, --raw-output      Print var_export-style return values (for non-interactive input)
      --compact         Run PsySH with compact output.
  -q, --quiet           Shhhhhh.
  -v|vv|vvv, --verbose  Increase the verbosity of messages.
      --yolo            Run PsySH without input validation. You don't want this.

EOL;

                exit($usageException === null ? 0 : 1);
            }

            // Handle --version
            if ($input->getOption('version')) {
                echo Shell::getVersionHeader($config->useUnicode()).\PHP_EOL;
                exit(0);
            }

            // Handle --self-update
            if ($input->getOption('self-update')) {
                if (!$shellIsPhar) {
                    \fwrite(\STDERR, 'The --self-update option can only be used with with a phar based install.'.\PHP_EOL);
                    exit(1);
                }
                $selfUpdate = new SelfUpdate(new GitHubChecker(), new Installer());
                $result = $selfUpdate->run($input, $config->getOutput());
                exit($result);
            }

            $shell = new Shell($config);

            // Pass additional arguments to Shell as 'includes'
            $shell->setIncludes($input->getArgument('include'));

            try {
                // And go!
                $shell->run();
            } catch (\Throwable $e) {
                \fwrite(\STDERR, $e->getMessage().\PHP_EOL);

                // @todo this triggers the "exited unexpectedly" logic in the
                // ForkingLoop, so we can't exit(1) after starting the shell...
                // fix this :)

                // exit(1);
            }
        };
    }
}
