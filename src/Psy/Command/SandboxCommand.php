<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SandboxCommand
 * @package Psy\Command
 */
class SandboxCommand extends Command
{
    const SANDBOX_FOLDER = 'psysh_sandbox';

    const ACTION_ADD = 'add';
    const ACTION_CREATE = 'create';
    const ACTION_LIST = 'list';
    const ACTION_WHICH = 'which';
    const ACTION_USE = 'use';
    const ACTION_SWITCH = 'switch';
    const ACTION_DELETE = 'delete';
    const ACTION_CLEAR = 'clear';
    const ACTION_EXIT = 'exit';
    const ACTION_RESET = 'reset';
    const ACTION_HELP = 'help';

    /**
     * @var String
     */
    protected $tempDir;

    /**
     * @var array
     */
    protected $sandboxes = array();

    /**
     * @var String
     */
    protected $restoreFolder;

    /**
     * @var string
     */
    protected $current;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sandbox')
            ->setDefinition(array(
                new InputArgument(
                    'action',
                    InputArgument::OPTIONAL,
                    'Action to perform : add|create, list, which, use|switch, clear|delete, exit|reset'
                ),
                new InputArgument('name', InputArgument::OPTIONAL),
                new InputOption(
                    'grep',
                    'g',
                    InputOption::VALUE_OPTIONAL,
                    'grep parameter'
                ),
            ))
            ->setDescription('Manages sandboxed environments.')
            ->setHelp(
                <<<HELP
sandbox add|create [name] creates a encapsulated sandbox with an optional name, if not provided will autogenerate a name.
sandbox list Will show a list of the currently generated sandboxes
sandbox which Will show the current sandbox
sandbox use|switch name Switches to sandbox by name,
sandbox clear|delete [name] Removes a sandbox by name, or the current if name is not provided.
sandbox exit|reset Erases all sandboxes and sets the normal state.
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $name = $input->getArgument('name');
        $action = $input->getArgument('action');

        $this->performAction($action, $name);
    }

    /**
     * @param $action
     * @param $name
     */
    protected function performAction($action, $name)
    {
        switch ($action) {
            case self::ACTION_ADD:
            case self::ACTION_CREATE:
                $identifier = $this->createSandbox($name);
                $this->switchSandbox($identifier);
                break;
            case self::ACTION_LIST:
                $this->listSandboxes();
                break;
            case self::ACTION_WHICH:
                $this->showCurrentSandbox();
                break;
            case self::ACTION_SWITCH:
            case self::ACTION_USE:
                $this->switchSandbox($name);
                break;
            case self::ACTION_DELETE:
            case self::ACTION_CLEAR:
                $this->removeSandbox($name);
                break;
            case self::ACTION_EXIT:
            case self::ACTION_RESET:
                $this->restoreState();
                break;
            case self::ACTION_HELP:
            default:
                $this->showUsage();
                break;
        }
    }

    /**
     * @param $dir
     */
    public function setTempDir($dir)
    {
        $this->tempDir = $dir;
    }

    /**
     *
     */
    protected function showUsage()
    {
        $this->output->writeln($this->asText());
    }

    /**
     * @param $name
     * @return string
     */
    protected function getSandboxPath($name)
    {
        return $this->tempDir . DIRECTORY_SEPARATOR . self::SANDBOX_FOLDER . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param  null        $name
     * @return null|string
     */
    protected function createSandbox($name = null)
    {
        if (is_null($this->restoreFolder)) {
            $this->restoreFolder = getcwd();
        }

        $name = !is_null($name) ? $name : uniqid();
        $path = $this->getSandboxPath($name);
        if (@file_exists($path)) {
            throw new \InvalidArgumentException("Sandbox with name : $name already exists.");
        }

        $result = @mkdir($path, 0777, true);
        if (!$result) {
            throw new \RuntimeException("Unable to create sandbox element.");
        }

        $this->output->writeln("<info>Created sandbox named : $name</info>");
        $this->sandboxes[$name] = $path;

        return $name;
    }

    /**
     *
     */
    protected function listSandboxes()
    {
        $grep = $this->input->getOption('grep');
        if (!is_null($grep)) {
            $this->output->writeln("<info>Retrieving sandbox list matching with : $grep</info>");
        } else {
            $this->output->writeln("<info>Retrieving sandbox list</info>");
        }
        foreach (array_keys($this->sandboxes) as $sandbox) {
            if (is_null($grep) || preg_match(sprintf('#%s#', preg_quote($grep)), $sandbox)) {
                $this->output->writeln("<info>Sandbox : $sandbox</info>");
            }
        }
    }

    /**
     *
     */
    protected function showCurrentSandbox()
    {
        if (is_null($this->current)) {
            throw new \RuntimeException("You are not currently on a sandbox.");
        }

        $this->output->writeln(sprintf("<info>Currently on sandbox : %s </info>", $this->current));
    }

    /**
     * @param $name
     */
    protected function removeSandbox($name = null)
    {
        if (is_null($name)) {
            if (is_null($this->current)) {
                throw new \RuntimeException("You are not currently on a sandbox.");
            }
            $name = $this->current;
        }
        $path = $this->getSandboxPath($name);
        if (!file_exists($path)) {
            throw new \RuntimeException("Sandbox named $name does not exist.");
        }

        $this->output->writeln("<info>Removing the sandbox $name</info>");

        @rmdir($path);
        unset($this->sandboxes[$name]);

        if ($name === $this->current) {
            unset($this->current);
            @chdir($this->restoreFolder);
        }
    }

    /**
     * @param $name
     */
    protected function switchSandbox($name)
    {
        if (!in_array($name, array_keys($this->sandboxes))) {
            throw new \InvalidArgumentException("Sandbox with name : $name does not exist.");
        }

        $this->output->writeln("<info>Switching to sandbox $name</info>");

        @chdir($this->sandboxes[$name]);
        $this->current = $name;
    }

    /**
     *
     */
    protected function clear()
    {
        foreach ($this->sandboxes as $id => $sandbox) {
            $this->removeSandbox($id);
        }

        unset($this->current);
    }

    /**
     *
     */
    protected function restoreState()
    {
        $this->clear();
        $this->output->writeln('<info>Restoring shell out from the sandbox state</info>');
        @chdir($this->restoreFolder);
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->clear();
        @chdir($this->restoreFolder);
    }
}
