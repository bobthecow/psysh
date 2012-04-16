<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\ShellAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PsyVersionCommand extends ShellAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('version')
            ->setDefinition(array())
            ->setDescription('Show PsySH version.')
            ->setHelp(<<<EOF
Show PsySH version.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln($this->shell->getVersion());
    }
}
