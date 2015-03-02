<?php

namespace Psy\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DemoCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('demo')
            ->setDefinition(array(
                new InputOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message to send.'),
            ))
            ->setDescription('Sample command just for testing.')
            ->setHelp(
                <<<HELP
HELP
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $message = $input->getOption('message');
        $output->writeln(sprintf('<info>Received message "%s". </info>', $message));
    }
}
