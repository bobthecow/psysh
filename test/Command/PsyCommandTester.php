<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2025 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * A lightweight CommandTester for commands that use page() methods.
 *
 * Some PsySH commands use $output->page() for paged output, which is not
 * available on standard Symfony Console outputs. This tester uses a custom
 * output class that supports the page() method.
 */
class PsyCommandTester
{
    private Command $command;
    private TestOutput $output;
    private ArrayInput $input;
    private int $statusCode = 0;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Executes the command.
     *
     * @param array $input An array of command arguments and options
     *
     * @return int The command exit code
     */
    public function execute(array $input): int
    {
        // set the command name automatically if the application requires
        // this argument and no command name was passed
        if (!isset($input['command'])
            && (null !== $application = $this->command->getApplication())
            && $application->getDefinition()->hasArgument('command')
        ) {
            $input = \array_merge(['command' => $this->command->getName()], $input);
        }

        $this->input = new ArrayInput($input);
        $this->input->setInteractive(false);

        $this->output = new TestOutput();
        $this->output->setDecorated(false);

        return $this->statusCode = $this->command->run($this->input, $this->output);
    }

    /**
     * Gets the display output.
     */
    public function getDisplay(): string
    {
        return $this->output->fetch();
    }

    /**
     * Gets the status code returned by the last execution.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
