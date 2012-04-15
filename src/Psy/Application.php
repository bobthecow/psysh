<?php

namespace Psy;

use Psy\Command;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Application extends BaseApplication
{
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
            new Command\DocCommand,
            new Command\ShowCommand,
            // new Command\ShowInputCommand,
            // new Command\ClearInputCommand,
            new Command\WtfCommand,
            new Command\PsyVersionCommand,
            new Command\ExitCommand,
        );

        if (function_exists('readline')) {
            $commands[] = new Command\HistoryCommand();
        }

        return $commands;
    }
}
