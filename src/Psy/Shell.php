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

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\Exception\ErrorException;
use Psy\Exception\Exception as PsyException;
use Psy\Exception\RuntimeException;
use Psy\Formatter\ArrayFormatter;
use Psy\Formatter\ObjectFormatter;
use Psy\Output\ShellOutput;
use Psy\ShellAware;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Psy Shell application
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
    const VERSION = 'v0.0.1-dev';

    const PROMPT      = '>>> ';
    const BUFF_PROMPT = '... ';
    const REPLAY      = '--> ';
    const RETVAL      = '=> ';

    private $config;
    private $cleaner;
    private $output;
    private $inputBuffer;
    private $code;
    private $codeBuffer;
    private $scopeVariables;
    private $exceptions;

    /**
     * Create a new Psy shell.
     *
     * @param Configuration $config (default: null)
     */
    public function __construct(Configuration $config = null)
    {
        $this->config         = $config ?: new Configuration;
        $this->cleaner        = $this->config->getCodeCleaner();
        $this->loop           = $this->config->getLoop();
        $this->scopeVariables = array();

        parent::__construct('PsySH', self::VERSION);

        $this->config->setShell($this);
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
        $commands = array(
            new Command\HelpCommand,
            new Command\ListCommand,
            new Command\ListClassesCommand,
            new Command\ListFunctionsCommand,
            new Command\DocCommand,
            new Command\ShowCommand,
            new Command\WtfCommand,
            new Command\TraceCommand,
            new Command\BufferCommand,
            // new Command\PsyVersionCommand,
        );

        if ($this->config->useReadline()) {
            $commands[] = new Command\HistoryCommand;
        }

        $commands[] = new Command\ExitCommand;

        return $commands;
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
     * @return integer 0 if everything went fine, or an error code
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if ($output === null) {
            $output = $this->config->getOutput();
        }

        return parent::run($input, $output);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return integer 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->setOutput($output);

        $this->exceptions = array();
        $this->resetCodeBuffer();

        $this->setAutoExit(false);
        $this->setCatchExceptions(true);

        if ($this->config->useReadline()) {
            readline_read_history($this->config->getHistoryFile());
            readline_completion_function(array($this, 'autocomplete'));
        }

        $this->output->writeln($this->getHeader());

        $this->loop->run($this);
    }

    /**
     * Read user input.
     *
     * This will continue fetching user input until the code buffer contains
     * valid code.
     */
    public function getInput()
    {
        do {
            // reset output verbosity (in case it was altered by a subcommand)
            $this->output->setVerbosity(ShellOutput::VERBOSITY_VERBOSE);

            $input = $this->readline();

            // handle Ctrl+D
            if ($input === false) {
                $this->output->writeln('');
                throw new BreakException('Ctrl+D');
            }

            // handle empty input
            if (!trim($input)) {
                continue;
            }

            if ($this->config->useReadline()) {
                readline_add_history($input);
                readline_write_history($this->config->getHistoryFile());
            }

            if ($this->hasCommand($input)) {
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
     * Set the variables currently in scope.
     *
     * @param array $vars
     */
    public function setScopeVariables(array $vars)
    {
        unset($vars['__psysh__']);
        $this->scopeVariables = $vars;
    }

    /**
     * Return the set of variables currently in scope.
     *
     * @return array Associative array of scope variables.
     */
    public function getScopeVariables()
    {
        return $this->scopeVariables;
    }

    /**
     * Get the set of variable names currently in scope.
     *
     * @return array Array of variable names.
     */
    public function getScopeVariableNames()
    {
        return array_keys($this->getScopeVariables());
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
        if (!array_key_exists($name, $this->scopeVariables)) {
            throw new \InvalidArgumentException('Unknown variable: $'.$name);
        }

        return $this->scopeVariables[$name];
    }

    /**
     * Get all exceptions caught by this shell instance.
     *
     * @return array
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * Get the last exception caught by this shell instance.
     *
     * @return Exception|null
     */
    public function getLastException()
    {
        return end($this->exceptions);
    }

    /**
     * Check whether this shell's code buffer contains code.
     *
     * @return bool True if the code buffer contains code.
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
     * @return bool True if the code buffer content is valid.
     */
    protected function hasValidCode()
    {
        return $this->code !== false;
    }

    /**
     * Add code to the code buffer.
     *
     * @param string $code
     */
    public function addCode($code)
    {
        $this->codeBuffer[] = $code;
        $this->code         = $this->cleaner->clean($this->codeBuffer);
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
     * Run a Psy shell command given the user input.
     *
     * @throws InvalidArgumentException if the input is not a valid command.
     *
     * @param string $input User input string
     *
     * @return mixed Who knows?
     */
    protected function runCommand($input)
    {
        $command = $this->getCommand($input);

        if (empty($command)) {
            throw new \InvalidArgumentException('Command not found: '.$input);
        }

        if ($command instanceof ShellAware) {
            $command->setShell($this);
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
     * @return string PHP code buffer contents.
     */
    public function flushCode()
    {
        if ($this->hasValidCode()) {
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
     * @return string Current code namespace.
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
     */
    public function writeStdout($out)
    {
        if (!empty($out)) {
            $this->output->writeln($out, ShellOutput::OUTPUT_RAW);
        }
    }

    /**
     * Write a return value to stdout.
     *
     * The return value is formatted or pretty-printed, and rendered in a
     * visibly distinct manner (in this case, as cyan).
     *
     * @see self::formatValue
     *
     * @param mixed $ret
     */
    public function writeReturnValue($ret)
    {
        $this->output->writeln(sprintf("%s<return>%s</return>", self::RETVAL, $this->formatValue($ret)));
    }

    /**
     * Write a caught Exception to stdout.
     *
     * @see self::renderException
     *
     * @param \Exception $e
     */
    public function writeException(\Exception $e)
    {
        $this->renderException($e, $this->output);
    }

    /**
     * Renders a caught Exception.
     *
     * Exceptions are formatted according to severity. ErrorExceptions which were
     * warnings or Strict errors aren't rendered as harshly as real errors.
     *
     * @param Exception       $e      An exception instance
     * @param OutputInterface $output An OutputInterface instance
     */
    public function renderException($e, $output)
    {
        $this->exceptions[] = $e;

        $message = $e->getMessage();
        if (!$e instanceof PsyException) {
            $message = sprintf('%s with message \'%s\'', get_class($e), $message);
        }

        $severity = 'error';
        if ($e instanceof \ErrorException) {
            switch ($e->getSeverity()) {
                case E_WARNING:
                case E_CORE_WARNING:
                case E_COMPILE_WARNING:
                case E_USER_WARNING:
                case E_STRICT:
                    $severity = 'warning';
                    break;
            }
        }

        $output->writeln(sprintf('<%s>%s</%s>', $severity, $message, $severity));

        $this->resetCodeBuffer();
    }

    /**
     * Format a value for display.
     *
     * If it's an object, the formatting is delegated to ObjectFormatter. If it's
     * an array, ArrayFormatter will do the job. If it's anything else, it is
     * JSON encoded for display.
     *
     * @see ObjectFormatter::format
     * @see ArrayFormatter::format
     * @see json_encode
     *
     * @param mixed $val
     *
     * @return string Formatted value
     */
    protected function formatValue($val)
    {
        if (is_object($val)) {
            return ObjectFormatter::format($val);
        } elseif (is_array($val)) {
            return ArrayFormatter::format($val);
        } else {
            return json_encode($val);
        }
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
     * @return bool True if the shell has a command for the given input.
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
     * @return string One line of user input.
     */
    protected function readline()
    {
        if (!empty($this->inputBuffer)) {
            $line = array_shift($this->inputBuffer);
            $this->output->writeln(sprintf('<aside>%s %s</aside>', self::REPLAY, $line));

            return $line;
        }

        if ($this->config->useReadline()) {
            return readline($this->getPrompt());
        } else {
            $this->output->write($this->getPrompt());

            return rtrim(fgets(STDIN, 1024));
        }
    }

    /**
     * Get the shell output header.
     *
     * @return string
     */
    protected function getHeader()
    {
        return sprintf(
            "<aside>PsySH %s (PHP %s — %s) by Justin Hileman</aside>",
            self::VERSION,
            phpversion(),
            php_sapi_name()
        );
    }

    /**
     * Get the current version of PsySH.
     *
     * @return string
     */
    public function getVersion()
    {
        return sprintf("PsySH %s (PHP %s — %s)", self::VERSION, phpversion(), php_sapi_name());
    }

    /**
     * Autocomplete variable names.
     *
     * This is used by `readline` for tab completion.
     *
     * @param string $text
     *
     * @return mixed Array possible completions for the given input, if any.
     */
    protected function autocomplete($text)
    {
        $info = readline_info();
        // $line = substr($info['line_buffer'], 0, $info['end']);

        // Check whether there's a command for this
        // $words = explod(' ', $line);
        // $firstWord = reset($words);

        // check whether this is a variable...
        $firstChar = substr($info['line_buffer'], max(0, $info['end'] - strlen($text) - 1), 1);
        if ($firstChar == '$') {
            return $this->getScopeVariableNames();
        }
    }
}
