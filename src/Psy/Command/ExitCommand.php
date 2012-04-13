<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Exception\BreakException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExitCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('exit')
            ->setAliases(array('quit', 'q'))
            ->setDefinition(array(
                new InputArgument('return', InputArgument::OPTIONAL, 'Optional return value', null),
            ))
            ->setDescription('End the current session and return to caller. Accepts optional return value.')
            ->setHelp(<<<EOL
It can be useful to exit a context with a user-provided value. For
instance an exit value can be used to determine program flow.

e.g.
<return>>>> exit "WHEEE!"</return>
<return>>>> exit</return>
EOL
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($return = $input->getArgument('return')) {
            $output->writeln('<error>Return values not yet implemented.</error>');
        }

        throw new BreakException('<strong>Goodbye.</strong>');
    }
}
