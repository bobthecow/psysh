<?php

namespace Psy;

use Psy\Configuration;
use Psy\Exception\BreakException;
use Psy\Exception\ErrorException;
use Psy\Exception\Exception as PsyException;
use Psy\Exception\RuntimeException;
use Psy\Formatter\ObjectFormatter;
use Psy\ShellAware;
use Psy\Util\LessPipe;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class Shell
{
    const VERSION = 'v0.0.1-dev';

    const PROMPT      = '>>> ';
    const BUFF_PROMPT = '... ';
    const REPLAY      = '--> ';
    const RETVAL      = '=> ';

    private $config;
    private $application;
    private $cleaner;
    private $output;
    private $pager;
    private $inputBuffer;
    private $code;
    private $codeBuffer;
    private $scopeVariables;
    private $exceptions;
    private $parentPid;
    private $forkHistoryFileName;
    private $forkHistoryFile;

    /**
     * Create a new Psy shell.
     *
     * @param Configuration $config (default: null)
     */
    public function __construct(Configuration $config = null)
    {
        $this->config         = $config ?: new Configuration;
        $this->application    = $this->config->getApplication();
        $this->cleaner        = $this->config->getCodeCleaner();
        $this->output         = $this->config->getOutput();
        $this->pager          = new LessPipe($this->output);
        $this->scopeVariables = array();
    }

    public function __destruct()
    {
        // last one out, turn off the lights
        if ($this->config->usePcntl()) {
            if (posix_getpid() == $this->parentPid) {
                fclose($this->forkHistoryFile);
                unlink($this->forkHistoryFileName);
            }
        }
    }

    public function run()
    {
        $this->exceptions = array();
        $this->forkHistory = array();
        $this->resetCodeBuffer();

        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(true);

        if ($this->config->useReadline()) {
            readline_read_history($this->config->getHistoryFile());
            readline_completion_function(array($this, 'autocomplete'));
        }

        if ($this->config->usePcntl()) {
            $this->parentPid = posix_getpid();
            $this->forkHistoryFileName = $this->config->getForkHistoryFile($this->parentPid);
            $this->forkHistoryFile = fopen($this->forkHistoryFileName, 'w+');
            $this->callsUntilFork = 0;
        }

        $this->output->writeln($this->getHeader());

        $loop = function($__psysh__) {
            extract($__psysh__->getScopeVariables());

            do {
                $__psysh__->fork();

                // a bit of housekeeping
                unset($__psysh_out__, $__psysh_e__);
                $__psysh__->setScopeVariables(get_defined_vars());

                try {
                    // read a line, see if we should eval
                    while (!$__psysh__->doLoop());

                    // evaluate the current code buffer
                    ob_start();

                    set_error_handler(array($__psysh__, 'throwErrorException'));
                    $_ = eval($__psysh__->flushCode());
                    restore_error_handler();

                    $__psysh_out__ = ob_get_contents();
                    ob_end_clean();

                    $__psysh__->writeStdout($__psysh_out__);
                    $__psysh__->writeReturnValue($_);
                } catch (BreakException $__psysh_e__) {
                    restore_error_handler();
                    $__psysh__->writeException($__psysh_e__);
                    return;
                } catch (\Exception $__psysh_e__) {
                    restore_error_handler();
                    $__psysh__->writeException($__psysh_e__);
                }
            } while (true);

        };

        $loop($this);
    }

    public function throwErrorException($errno, $errstr, $errfile, $errline)
    {
        throw new ErrorException($errstr, $errno, $errno, $errfile, $errline);
    }

    public function fork()
    {
        if ($this->config->usePcntl()) {
            if (--$this->callsUntilFork <= 0) {
                $this->callsUntilFork = $this->config->getForkEveryN();
                if (pcntl_fork()) {
                    // wait for the child
                    pcntl_wait($status);

                    // did it exit?
                    if (!pcntl_wifexited($status)) {
                        $this->writeException(new RuntimeException('<error>ABNORMAL EXIT</error>'));
                        die;
                    }

                    // did it succeed?
                    if (!pcntl_wexitstatus($status)) {
                        exit;
                    }

                    // try to recover?
                    $this->recoverFromFatalError();
                } else {
                    $this->clearForkHistory();
                }
            } else {
                $this->writeForkHistory();
            }
        }
    }

    private function recoverFromFatalError()
    {
        $lines = $this->readForkHistory();
        $count = count($lines);
        if ($count == 0) {
            return;
        }

        $this->output->writeln(<<<EOD

<error>PsySH has detected (and prevented) a fatal error.</error>

You have done $count things since your last save point:

EOD
        );
        $this->output->writeln($lines, Output::NUMBER_LINES);
        $this->output->writeln('');

        $dialog = $this->application->getHelperSet()->get('dialog');
        if ($dialog->askConfirmation($this->output, '<question>Should we try to replay these commands?</question>', false)) {
            $this->output->writeln('Got it. Replaying.');
            $this->addInput($lines);
        } else {
            $this->output->writeln('Got it. Try not to do that again, eh?');
        }

        // clear out the history and fork immediately.
        $this->clearForkHistory();
        $this->callsUntilFork = 0;
    }

    public function doLoop()
    {
        // reset output verbosity (in case it was altered by a subcommand)
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        $input = $this->readline();

        // handle Ctrl+D
        if ($input === false) {
            $this->output->writeln('');
            throw new BreakException('Ctrl+D');
        }

        // handle empty input
        if (!trim($input)) {
            return false;
        }

        if ($this->config->useReadline()) {
            readline_add_history($input);
            readline_write_history($this->config->getHistoryFile());
        }

        if ($this->hasCommand($input)) {
            $this->runCommand($input);

            return false;
        }

        $this->addCode($input);

        return $this->hasValidCode();
    }

    public function setScopeVariables(array $vars)
    {
        unset($vars['__psysh__']);
        $this->scopeVariables = $vars;
    }

    public function getScopeVariables()
    {
        return $this->scopeVariables;
    }

    public function getScopeVariableNames()
    {
        return array_keys($this->getScopeVariables());
    }

    public function getScopeVariable($name)
    {
        if (!array_key_exists($name, $this->scopeVariables)) {
            throw new \InvalidArgumentException('Unknown variable: '.$name);
        }

        return $this->scopeVariables[$name];
    }

    public function getExceptions()
    {
        return $this->exceptions;
    }

    public function getLastException()
    {
        return end($this->exceptions);
    }

    protected function hasCode()
    {
        return !empty($this->codeBuffer);
    }

    protected function hasValidCode()
    {
        return $this->code !== false;
    }

    public function addCode($code)
    {
        $this->codeBuffer[] = $code;
        $this->code         = $this->cleaner->clean($this->codeBuffer);
    }

    public function getCodeBuffer()
    {
        return $this->codeBuffer;
    }

    protected function runCommand($input)
    {
        $command = $this->getCommand($input);
        if ($command instanceof ShellAware) {
            $command->setShell($this);
        }

        $input = str_replace('\\', '\\\\', rtrim($input, " \t\n\r\0\x0B;"));
        $command->run(new StringInput($input), $this->output);
    }

    public function resetCodeBuffer()
    {
        $this->codeBuffer  = array();
        $this->code        = false;
    }

    public function addInput($input)
    {
        foreach ((array) $input as $line) {
            $this->inputBuffer[] = $line;
        }
    }

    public function flushCode()
    {
        if ($this->hasValidCode()) {
            $code = $this->code;
            $this->forkHistory[] = implode(PHP_EOL, $this->codeBuffer);
            $this->resetCodeBuffer();

            return $code;
        }
    }

    public function writeStdout($out)
    {
        if (!empty($out)) {
            $this->output->writeln($out, OutputInterface::OUTPUT_RAW);
        }
    }

    public function writeReturnValue($ret)
    {
        $returnString = $this->formatValue($ret);
        if (strpos($returnString, '</return>') === false) {
            $this->output->writeln(sprintf("%s<return>%s</return>", self::RETVAL, $returnString));
        } else {
            $this->output->writeln(sprintf("%s%s", self::RETVAL, $returnString), OutputInterface::OUTPUT_RAW);
        }
    }

    public function writeException(\Exception $e)
    {
        $this->exceptions[] = $e;

        $message = $e->getMessage();
        if (!$e instanceof PsyException) {
            $message = sprintf('%s with message \'%s\'', get_class($e), $message);
        }

        $this->output->writeln(sprintf('<error>%s</error>', $message));

        $this->resetCodeBuffer();
    }

    protected function formatValue($val)
    {
        // uppercase null is ugly.
        if ($val === null) {
            return 'null';
        } elseif (is_object($val)) {
            return ObjectFormatter::format($val);
        } else {
            return var_export($val, true);
        }
    }

    protected function getCommand($command)
    {
        $matches = array();
        if (preg_match('/^\s*([^\s]+)(?:\s|$)/', $command, $matches)) {
            return $this->application->get($matches[1]);
        }
    }

    protected function hasCommand($command)
    {
        $matches = array();
        if (preg_match('/^\s*([^\s]+)(?:\s|$)/', $command, $matches)) {
            return $this->application->has($matches[1]);
        }

        return false;
    }

    protected function getPrompt()
    {
        return $this->hasCode() ? self::BUFF_PROMPT : self::PROMPT;
    }

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

    protected function getHeader()
    {
        return sprintf(
            "<aside>PsySH %s (PHP %s — %s) by Justin Hileman</aside>",
            self::VERSION,
            phpversion(),
            php_sapi_name()
        );
    }

    public function getVersion()
    {
        return sprintf("PsySH %s (PHP %s — %s)", self::VERSION, phpversion(), php_sapi_name());
    }

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

    private function writeForkHistory()
    {
        ftruncate($this->forkHistoryFile, 0);
        fwrite($this->forkHistoryFile, implode(PHP_EOL, $this->forkHistory));
    }

    private function clearForkHistory()
    {
        ftruncate($this->forkHistoryFile, 0);
        $this->forkHistory = array();
    }

    private function readForkHistory()
    {
        $content = trim(file_get_contents($this->forkHistoryFileName));

        return empty($content) ? array() : explode(PHP_EOL, $content);
    }
}
