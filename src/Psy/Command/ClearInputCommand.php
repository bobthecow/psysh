<?php

namespace Psy\Command;

use Psy\Command\Command;
use Psy\Shell;
use Psy\ShellAware;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearInputCommand extends Command implements ShellAware
{
    private $shell;

    public function setShell(Shell $shell)
    {
        $this->shell = $shell;
    }

    protected function configure()
    {
        $this
            ->setName('clear-input')
            ->setAliases(array('clear'))
            ->setDefinition(array())
            ->setDescription('Clear the contents of the input buffer.')
            ->setHelp(<<<EOF
Clear the contents of the input buffer for the current multi-line expression.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writelnnos($this->shell->getCodeBuffer(), 0, 'urgent');
        $this->shell->resetCodeBuffer();
    }
}
