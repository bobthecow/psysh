<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2026 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Command;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Help command.
 *
 * Lists available commands, and gives command-specific help when asked nicely.
 */
class HelpCommand extends Command
{
    private ?Command $command = null;
    private ?InputInterface $commandInput = null;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('help')
            ->setAliases(['?'])
            ->setDefinition([
                new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name.', null),
            ])
            ->setDescription('Show a list of commands. Type `help [foo]` for information about [foo].')
            ->setHelp('My. How meta.');
    }

    /**
     * Helper for setting a subcommand to retrieve help for.
     *
     * @param Command $command
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Helper for preserving the original input when rendering contextual help.
     */
    public function setCommandInput(InputInterface $input): void
    {
        $this->commandInput = $input;
    }

    /**
     * {@inheritdoc}
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shellOutput = $this->shellOutput($output);

        if ($this->command !== null) {
            // help for an individual command
            $shellOutput->page($this->command->asTextForInput($this->commandInput ?? $input));
            $this->command = null;
            $this->commandInput = null;
        } elseif ($name = $input->getArgument('command_name')) {
            // help for an individual command
            try {
                $cmd = $this->getApplication()->get($name);
            } catch (CommandNotFoundException $e) {
                $this->getShell()->writeException($e);
                $output->writeln('');
                $output->writeln(\sprintf(
                    '<aside>To read PHP documentation, use <return>doc %s</return></aside>',
                    $name
                ));
                $output->writeln('');

                return 1;
            }

            if (!$cmd instanceof Command) {
                throw new \RuntimeException(\sprintf('Expected Psy\Command\Command instance, got %s', \get_class($cmd)));
            }

            $shellOutput->page($cmd->asTextForInput($input));
        } else {
            $this->commandInput = null;
            // list available commands
            $commands = $this->getApplication()->all();

            $table = $this->getTable($output);

            foreach ($commands as $name => $command) {
                if ($name !== $command->getName()) {
                    continue;
                }

                if ($command->getAliases()) {
                    $aliases = \sprintf('<comment>Aliases:</comment> %s', \implode(', ', $command->getAliases()));
                } else {
                    $aliases = '';
                }

                $table->addRow([
                    \sprintf('<info>%s</info>', $name),
                    $command->getDescription(),
                    $aliases,
                ]);
            }

            $shellOutput->startPaging();

            $table->render();

            $shellOutput->stopPaging();
        }

        return 0;
    }
}
