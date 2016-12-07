<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2015 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy;

use Psy\Exception\BreakException;
use Psy\Exception\ErrorException;
use Psy\Exception\Exception as PsyException;
use Psy\Exception\ThrowUpException;
use Psy\Output\ShellOutput;
use Psy\TabCompletion\Matcher;
use Psy\VarDumper\PresenterAware;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Psy Shell application.
 *
 * Usage:
 *
 *     $shell = new Shell;
 *     $shell->run();
 *
 * @author Justin Hileman <justin@justinhileman.info>
 */
class Shell extends Application
{
    const VERSION = 'v0.8.0';

    const PROMPT      = '>>> ';
    const BUFF_PROMPT = '... ';
    const REPLAY      = '--> ';
    const RETVAL      = '=> ';

    private $config;
    private $cleaner;
    private $output;
    private $readline;
    private $inputBuffer;
    private $code;
    private $codeBuffer;
    private $codeBufferOpen;
    private $context;
    private $includes;
    private $loop;
    private $outputWantsNewline = false;
    private $completion;
    private $tabCompletionMatchers = array();

    /**
     * Create a new Psy Shell.
     *
     * @param Configuration $config (default: null)
     */
    public function __construct(Configuration $config = null)
    {
        $this->config   = $config ?: new Configuration();
        $this->cleaner  = $this->config->getCodeCleaner();
        $this->loop     = $this->config->getLoop();
        $this->context  = new Context();
        $this->includes = array();
        $this->readline = $this->config->getReadline();

        parent::__construct('Psy Shell', self::VERSION);

        $this->config->setShell($this);
    }

    /**
     * Check whether the first thing in a backtrace is an include call.
     *
     * This is used by the psysh bin to decide whether to start a shell on boot,
     * or to simply autoload the library.
     */
    public static function isIncluded(array $trace)
    {
        return isset($trace[0]['function']) &&
          in_array($trace[0]['function'], array('require', 'include', 'require_once', 'include_once'));
    }

    /**
     * Invoke a Psy Shell from the current context.
     *
     * For example:
     *
     *     foreach ($items as $item) {
     *         \Psy\Shell::debug(get_defined_vars());
     *     }
     *
     * If you would like your shell interaction to affect the state of the
     * current context, you can extract() the values returned from this call:
     *
     *     foreach ($items as $item) {
     *         extract(\Psy\Shell::debug(get_defined_vars()));
     *         var_dump($item); // will be whatever you set $item to in Psy Shell
     *     }
     *
     * Optionally, supply an object as the `$bind` parameter. This determines
     * the value `$this` will have in the shell, and sets up class scope so that
     * private and protected members are accessible:
     *
     *     class Foo {
     *         function bar() {
     *             \Psy\Shell::debug(get_defined_vars(), $this);
     *         }
     *     }
     *
     * This only really works in PHP 5.4+ and HHVM 3.5+, so upgrade already.
     *
     * @param array  $vars Scope variables from the calling context (default: array())
     * @param object $bind Bound object ($this) value for the shell
     *
     * @return array Scope variables from the debugger session
     */
    public static function debug(array $vars = array(), $bind = null)
    {
        echo PHP_EOL;

        if ($bind !== null) {
            $vars['this'] = $bind;
        }

        $sh = new \Psy\Shell();
        $sh->setScopeVariables($vars);
        $sh->run();

        return $sh->getScopeVariables();
    }

    /**
     * Adds a command object.
     *
     * {@inheritdoc}
     *
     * @param BaseCommand $command A Symfony Console Command object
     *
     * @return BaseCommand The registered command
     */
    public function add(BaseCommand $command)
    {
        if ($ret = parent::add($command)) {
            if ($ret instanceof ContextAware) {
                $ret->setContext($this->context);
            }

            if ($ret instanceof PresenterAware) {
                $ret->setPresenter($this->config->getPresenter());
            }
        }

        return $ret;
    }

    /**
     * Gets the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message.'),
        ));
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $hist = new Command\HistoryCommand();
        $hist->setReadline($this->readline);

        return array(
            new Command\HelpCommand(),
            new Command\ListCommand(),
            new Command\DumpCommand(),
            new Command\DocCommand(),
            new Command\ShowCommand($this->config->colorMode()),
            new Command\WtfCommand(),
            new Command\WhereamiCommand($this->config->colorMode()),
            new Command\ThrowUpCommand(),
            new Command\TraceCommand(),
            new Command\BufferCommand(),
            new Command\ClearCommand(),
            // new Command\PsyVersionCommand(),
            $hist,
            new Command\ExitCommand(),
        );
    }

    /**
     * @return array
     */
    protected function getTabCompletionMatchers()
    {
        if (empty($this->tabCompletionMatchers)) {
            $this->tabCompletionMatchers = array(
                new Matcher\CommandsMatcher($this->all()),
                new Matcher\KeywordsMatcher(),
                new Matcher\VariablesMatcher(),
                new Matcher\ConstantsMatcher(),
                new Matcher\FunctionsMatcher(),
                new Matcher\ClassNamesMatcher(),
                new Matcher\ClassMethodsMatcher(),
                new Matcher\ClassAttributesMatcher(),
                new Matcher\ObjectMethodsMatcher(),
                new Matcher\ObjectAttributesMatcher(),
            );
        }

        return $this->tabCompletionMatchers;
    }

    /**
     * @param array $matchers
     */
    public function addTabCompletionMatchers(array $matchers)
    {
        $this->tabCompletionMatchers = array_merge($matchers, $this->getTabCompletionMatchers());
    }

    /**
     * Set the Shell output.
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $this->initializeTabCompletion();

        if ($input === null && !isset($_SERVER['argv'])) {
            $input = new ArgvInput(array());
        }

        if ($output === null) {
            $output = $this->config->getOutput();
        }

        try {
            return parent::run($input, $output);
        } catch (\Exception $e) {
            $this->writeException($e);
        }
    }

    /**
     * Runs the current application.
     *
     * @throws Exception if thrown via the `throw-up` command
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        $this->resetCodeBuffer();

        $this->setAutoExit(false);
        $this->setCatchExceptions(false);

        $this->readline->readHistory();

        // if ($this->config->useReadline()) {
        //     readline_completion_function(array($this, 'autocomplete'));
        // }

        $this->output->writeln($this->getHeader());
        $this->writeVersionInfo();

        try {
            $this->loop->run($this);
        } catch (ThrowUpException $e) {
            throw $e->getPrevious();
        }
    }

    /**
     * Read user input.
     *
     * This will continue fetching user input until the code buffer contains
     * valid code.
     *
     * @throws BreakException if user hits Ctrl+D
     */
    public function getInput()
    {
        $this->codeBufferOpen = false;

        do {
            // reset output verbosity (in case it was altered by a subcommand)
            $this->output->setVerbosity(ShellOutput::VERBOSITY_VERBOSE);

            $input = $this->readline();

            /*
             * Handle Ctrl+D. It behaves differently in different cases:
             *
             *   1) In an expression, like a function or "if" block, clear the input buffer
             *   2) At top-level session, behave like the exit command
             */
            if ($input === false) {
                $this->output->writeln('');

                if ($this->hasCode()) {
                    $this->resetCodeBuffer();
                } else {
                    throw new BreakException('Ctrl+D');
                }
            }

            // handle empty input
            if (trim($input) === '') {
                continue;
            }

            if ($this->hasCommand($input)) {
                $this->readline->addHistory($input);
                $this->runCommand($input);
                continue;
            }

            $this->addCode($input);
        } while (!$this->hasValidCode());
    }

    /**
     * Pass the beforeLoop callback through to the Loop instance.
     *
     * @see Loop::beforeLoop
     */
    public function beforeLoop()
    {
        $this->loop->beforeLoop();
    }

    /**
     * Pass the afterLoop callback through to the Loop instance.
     *
     * @see Loop::afterLoop
     */
    public function afterLoop()
    {
        $this->loop->afterLoop();
    }

    /**
     * Set the variables currently in scope.
     *
     * @param array $vars
     */
    public function setScopeVariables(array $vars)
    {
        $this->context->setAll($vars);
    }

    /**
     * Return the set of variables currently in scope.
     *
     * @return array Associative array of scope variables
     */
    public function getScopeVariables()
    {
        return $this->context->getAll();
    }

    /**
     * Get the set of variable names currently in scope.
     *
     * @return array Array of variable names
     */
    public function getScopeVariableNames()
    {
        return array_keys($this->context->getAll());
    }

    /**
     * Get a scope variable value by name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getScopeVariable($name)
    {
        return $this->context->get($name);
    }

    /**
     * Add includes, to be parsed and executed before running the interactive shell.
     *
     * @param array $includes
     */
    public function setIncludes(array $includes = array())
    {
        $this->includes = $includes;
    }

    /**
     * Get PHP files to be parsed and executed before running the interactive shell.
     *
     * @return array
     */
    public function getIncludes()
    {
        return array_merge($this->config->getDefaultIncludes(), $this->includes);
    }

    /**
     * Check whether this shell's code buffer contains code.
     *
     * @return bool True if the code buffer contains code
     */
    public function hasCode()
    {
        return !empty($this->codeBuffer);
    }

    /**
     * Check whether the code in this shell's code buffer is valid.
     *
     * If the code is valid, the code buffer should be flushed and evaluated.
     *
     * @return bool True if the code buffer content is valid
     */
    protected function hasValidCode()
    {
        return !$this->codeBufferOpen && $this->code !== false;
    }

    /**
     * Add code to the code buffer.
     *
     * @param string $code
     */
    public function addCode($code)
    {
        try {
            // Code lines ending in \ keep the buffer open
            if (substr(rtrim($code), -1) === '\\') {
                $this->codeBufferOpen = true;
                $code = substr(rtrim($code), 0, -1);
            } else {
                $this->codeBufferOpen = false;
            }

            $this->codeBuffer[] = $code;
            $this->code         = $this->cleaner->clean($this->codeBuffer, $this->config->requireSemicolons());
        } catch (\Exception $e) {
            // Add failed code blocks to the readline history.
            $this->readline->addHistory(implode("\n", $this->codeBuffer));
            throw $e;
        }
    }

    /**
     * Get the current code buffer.
     *
     * This is useful for commands which manipulate the buffer.
     *
     * @return array
     */
    public function getCodeBuffer()
    {
        return $this->codeBuffer;
    }

    /**
     * Run a Psy Shell command given the user input.
     *
     * @throws InvalidArgumentException if the input is not a valid command
     *
     * @param string $input User input string
     *
     * @return mixed Who knows?
     */
    protected function runCommand($input)
    {
        $command = $this->getCommand($input);

        if (empty($command)) {
            throw new \InvalidArgumentException('Command not found: ' . $input);
        }

        $input = new StringInput(str_replace('\\', '\\\\', rtrim($input, " \t\n\r\0\x0B;")));

        if ($input->hasParameterOption(array('--help', '-h'))) {
            $helpCommand = $this->get('help');
            $helpCommand->setCommand($command);

            return $helpCommand->run($input, $this->output);
        }

        return $command->run($input, $this->output);
    }

    /**
     * Reset the current code buffer.
     *
     * This should be run after evaluating user input, catching exceptions, or
     * on demand by commands such as BufferCommand.
     */
    public function resetCodeBuffer()
    {
        $this->codeBuffer = array();
        $this->code       = false;
    }

    /**
     * Inject input into the input buffer.
     *
     * This is useful for commands which want to replay history.
     *
     * @param string|array $input
     */
    public function addInput($input)
    {
        foreach ((array) $input as $line) {
            $this->inputBuffer[] = $line;
        }
    }

    /**
     * Flush the current (valid) code buffer.
     *
     * If the code buffer is valid, resets the code buffer and returns the
     * current code.
     *
     * @return string PHP code buffer contents
     */
    public function flushCode()
    {
        if ($this->hasValidCode()) {
            $this->readline->addHistory(implode("\n", $this->codeBuffer));
            $code = $this->code;
            $this->resetCodeBuffer();

            return $code;
        }
    }

    /**
     * Get the current evaluation scope namespace.
     *
     * @see CodeCleaner::getNamespace
     *
     * @return string Current code namespace
     */
    public function getNamespace()
    {
        if ($namespace = $this->cleaner->getNamespace()) {
            return implode('\\', $namespace);
        }
    }

    /**
     * Write a string to stdout.
     *
     * This is used by the shell loop for rendering output from evaluated code.
     *
     * @param string $out
     * @param int    $phase Output buffering phase
     */
    public function writeStdout($out, $phase = PHP_OUTPUT_HANDLER_END)
    {
        $isCleaning = false;
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $isCleaning = $phase & PHP_OUTPUT_HANDLER_CLEAN;
        }

        // Incremental flush
        if ($out !== '' && !$isCleaning) {
            $this->output->write($out, false, ShellOutput::OUTPUT_RAW);
            $this->outputWantsNewline = (substr($out, -1) !== "\n");
        }

        // Output buffering is done!
        if ($this->outputWantsNewline && $phase & PHP_OUTPUT_HANDLER_END) {
            $this->output->writeln(sprintf('<aside>%s</aside>', $this->config->useUnicode() ? '⏎' : '\\n'));
            $this->outputWantsNewline = false;
        }
    }

    /**
     * Write a return value to stdout.
     *
     * The return value is formatted or pretty-printed, and rendered in a
     * visibly distinct manner (in this case, as cyan).
     *
     * @see self::presentValue
     *
     * @param mixed $ret
     */
    public function writeReturnValue($ret)
    {
        $this->context->setReturnValue($ret);
        $ret    = $this->presentValue($ret);
        $indent = str_repeat(' ', strlen(self::RETVAL));

        $this->output->writeln(self::RETVAL . str_replace(PHP_EOL, PHP_EOL . $indent, $ret));
    }

    /**
     * Renders a caught Exception.
     *
     * Exceptions are formatted according to severity. ErrorExceptions which were
     * warnings or Strict errors aren't rendered as harshly as real errors.
     *
     * Stores $e as the last Exception in the Shell Context.
     *
     * @param \Exception      $e      An exception instance
     * @param OutputInterface $output An OutputInterface instance
     */
    public function writeException(\Exception $e)
    {
        $this->context->setLastException($e);

        $message = $e->getMessage();
        if (!$e instanceof PsyException) {
            $message = sprintf('%s with message \'%s\'', get_class($e), $message);
        }

        $severity = ($e instanceof \ErrorException) ? $this->getSeverity($e) : 'error';
        $this->output->writeln(sprintf('<%s>%s</%s>', $severity, OutputFormatter::escape($message), $severity));

        $this->resetCodeBuffer();
    }

    /**
     * Helper for getting an output style for the given ErrorException's level.
     *
     * @param \ErrorException $e
     *
     * @return string
     */
    protected function getSeverity(\ErrorException $e)
    {
        $severity = $e->getSeverity();
        if ($severity & error_reporting()) {
            switch ($severity) {
                case E_WARNING:
                case E_NOTICE:
                case E_CORE_WARNING:
                case E_COMPILE_WARNING:
                case E_USER_WARNING:
                case E_USER_NOTICE:
                case E_STRICT:
                    return 'warning';

                default:
                    return 'error';
            }
        } else {
            // Since this is below the user's reporting threshold, it's always going to be a warning.
            return 'warning';
        }
    }

    /**
     * Helper for throwing an ErrorException.
     *
     * This allows us to:
     *
     *     set_error_handler(array($psysh, 'handleError'));
     *
     * Unlike ErrorException::throwException, this error handler respects the
     * current error_reporting level; i.e. it logs warnings and notices, but
     * doesn't throw an exception unless it's above the current error_reporting
     * threshold. This should probably only be used in the inner execution loop
     * of the shell, as most of the time a thrown exception is much more useful.
     *
     * If the error type matches the `errorLoggingLevel` config, it will be
     * logged as well, regardless of the `error_reporting` level.
     *
     * @see \Psy\Exception\ErrorException::throwException
     * @see \Psy\Shell::writeException
     *
     * @throws \Psy\Exception\ErrorException depending on the current error_reporting level
     *
     * @param int    $errno   Error type
     * @param string $errstr  Message
     * @param string $errfile Filename
     * @param int    $errline Line number
     */
    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if ($errno & error_reporting()) {
            ErrorException::throwException($errno, $errstr, $errfile, $errline);
        } elseif ($errno & $this->config->errorLoggingLevel()) {
            // log it and continue...
            $this->writeException(new ErrorException($errstr, 0, $errno, $errfile, $errline));
        }
    }

    /**
     * Format a value for display.
     *
     * @see Presenter::present
     *
     * @param mixed $val
     *
     * @return string Formatted value
     */
    protected function presentValue($val)
    {
        return $this->config->getPresenter()->present($val);
    }

    /**
     * Get a command (if one exists) for the current input string.
     *
     * @param string $input
     *
     * @return null|Command
     */
    protected function getCommand($input)
    {
        $input = new StringInput($input);
        if ($name = $input->getFirstArgument()) {
            return $this->get($name);
        }
    }

    /**
     * Check whether a command is set for the current input string.
     *
     * @param string $input
     *
     * @return bool True if the shell has a command for the given input
     */
    protected function hasCommand($input)
    {
        $input = new StringInput($input);
        if ($name = $input->getFirstArgument()) {
            return $this->has($name);
        }

        return false;
    }

    /**
     * Get the current input prompt.
     *
     * @return string
     */
    protected function getPrompt()
    {
        return $this->hasCode() ? self::BUFF_PROMPT : self::PROMPT;
    }

    /**
     * Read a line of user input.
     *
     * This will return a line from the input buffer (if any exist). Otherwise,
     * it will ask the user for input.
     *
     * If readline is enabled, this delegates to readline. Otherwise, it's an
     * ugly `fgets` call.
     *
     * @return string One line of user input
     */
    protected function readline()
    {
        if (!empty($this->inputBuffer)) {
            $line = array_shift($this->inputBuffer);
            $this->output->writeln(sprintf('<aside>%s %s</aside>', self::REPLAY, OutputFormatter::escape($line)));

            return $line;
        }

        return $this->readline->readline($this->getPrompt());
    }

    /**
     * Get the shell output header.
     *
     * @return string
     */
    protected function getHeader()
    {
        return sprintf('<aside>%s by Justin Hileman</aside>', $this->getVersion());
    }

    /**
     * Get the current version of Psy Shell.
     *
     * @return string
     */
    public function getVersion()
    {
        $separator = $this->config->useUnicode() ? '—' : '-';

        return sprintf('Psy Shell %s (PHP %s %s %s)', self::VERSION, phpversion(), $separator, php_sapi_name());
    }

    /**
     * Get a PHP manual database instance.
     *
     * @return PDO|null
     */
    public function getManualDb()
    {
        return $this->config->getManualDb();
    }

    /**
     * Autocomplete variable names.
     *
     * This is used by `readline` for tab completion.
     *
     * @param string $text
     *
     * @return mixed Array possible completions for the given input, if any
     */
    protected function autocomplete($text)
    {
        $info = readline_info();
        // $line = substr($info['line_buffer'], 0, $info['end']);

        // Check whether there's a command for this
        // $words = explode(' ', $line);
        // $firstWord = reset($words);

        // check whether this is a variable...
        $firstChar = substr($info['line_buffer'], max(0, $info['end'] - strlen($text) - 1), 1);
        if ($firstChar === '$') {
            return $this->getScopeVariableNames();
        }
    }

    /**
     * Initialize tab completion matchers.
     *
     * If tab completion is enabled this adds tab completion matchers to the
     * auto completer and sets context if needed.
     */
    protected function initializeTabCompletion()
    {
        // auto completer needs shell to be linked to configuration because of the context aware matchers
        if ($this->config->getTabCompletion()) {
            $this->completion = $this->config->getAutoCompleter();
            $this->addTabCompletionMatchers($this->config->getTabCompletionMatchers());
            foreach ($this->getTabCompletionMatchers() as $matcher) {
                if ($matcher instanceof ContextAware) {
                    $matcher->setContext($this->context);
                }
                $this->completion->addMatcher($matcher);
            }
            $this->completion->activate();
        }
    }

    /**
     * @todo Implement self-update
     * @todo Implement prompt to start update
     *
     * @return void|string
     */
    protected function writeVersionInfo()
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        try {
            $client = $this->config->getChecker();
            if (!$client->isLatest()) {
                $this->output->writeln(sprintf('New version is available (current: %s, latest: %s)',self::VERSION, $client->getLatest()));
            }
        } catch (\InvalidArgumentException $e) {
            $this->output->writeln($e->getMessage());
        }
    }
}
